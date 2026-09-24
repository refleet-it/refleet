<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shift\Infrastructure\Api;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Tests\Helpers\Messenger\DrainsCrossContextQueues;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Proves the actual point of the Qualification/Shift split: a Shift's target set can be
 * resolved 3 different ways (all currently-qualified targets of a Qualification, an
 * explicit subset of a Qualification's targets regardless of status, or a fully manual
 * project selection with no Qualification at all) — driven purely through the public
 * HTTP API, each mode carried all the way to a completed shift where that makes sense.
 */
final class ShiftFlowTest extends WebTestCase
{
    use DrainsCrossContextQueues;
    use Factories;
    use ResetDatabase;

    #[Test]
    public function mode_1_shifts_every_currently_qualified_target_and_completes_the_change(): void
    {
        // Arrange: architect session, runner API key, two projects
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $this->drainCrossContextQueues();
        $this->authenticateAs($client, $account);

        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        $projectA = $this->registerProject($client, 'Payments Service', '1', 'backend-team/payments-service');
        $projectB = $this->registerProject($client, 'Notifications Service', '2', 'backend-team/notifications-service');

        $client->jsonRequest('POST', '/api/identity/api-keys', ['name' => 'CI runner fleet']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $runnerAuthHeader = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->decode($client->getResponse())['token']];

        // Arrange: a completed qualification where both projects end up QUALIFIED
        $qualificationId = $this->createAndStartQualification($client, [$projectA, $projectB]);
        $this->claimAndReportQualificationJob($client, $runnerAuthHeader, true);
        $this->claimAndReportQualificationJob($client, $runnerAuthHeader, true);
        $client->jsonRequest('GET', '/api/qualifications/'.$qualificationId);
        Assert::assertSame('completed', $this->decode($client->getResponse())['status'] ?? null);

        // Act: mode 1 — qualificationId only, no projectIds
        $client->jsonRequest('POST', '/api/shifts', [
            'title' => 'Bump acme/legacy-lib to v3',
            'qualificationId' => $qualificationId,
        ]);

        // Assert: both currently-qualified targets carried over
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $createdShift = $this->decode($client->getResponse());
        $shiftId = $createdShift['id'];
        Assert::assertSame($qualificationId, $createdShift['qualificationId']);
        Assert::assertSame(2, $createdShift['targetCount']);

        $client->jsonRequest('GET', \sprintf('/api/shifts/%s/targets', $shiftId));
        $shiftTargets = $this->decode($client->getResponse())['targets'] ?? [];
        Assert::assertCount(2, $shiftTargets);
        $shiftProjectIds = \array_column($shiftTargets, 'projectId');
        Assert::assertContains($projectA, $shiftProjectIds);
        Assert::assertContains($projectB, $shiftProjectIds);

        // Act: define and start the change
        $client->jsonRequest('POST', \sprintf('/api/shifts/%s/change', $shiftId), [
            'changeMode' => 'ai',
            'changePrompt' => 'Bump acme/legacy-lib to ^3.0',
        ]);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        $client->jsonRequest('POST', \sprintf('/api/shifts/%s/change/start', $shiftId));
        $this->drainCrossContextQueues();
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        Assert::assertSame('applying_change', $this->decode($client->getResponse())['status'] ?? null);

        // Act: runner claims and reports both change jobs — each report carries the merge
        // request it opened — then "merged" reports complete them, as the poller would
        for ($i = 0; $i < 2; ++$i) {
            $client->jsonRequest('POST', '/api/runner/jobs/claim', ['runnerId' => 'runner-fleet-01'], $runnerAuthHeader);
            Assert::assertSame(200, $client->getResponse()->getStatusCode());
            $claimed = $this->decode($client->getResponse());
            Assert::assertSame('change', $claimed['kind'] ?? null);

            $client->jsonRequest('POST', \sprintf('/api/runner/jobs/%s/report', $claimed['jobId']), [
                'runnerId' => 'runner-fleet-01',
                'outcome' => 'success',
                'summary' => 'Bumped acme/legacy-lib to ^3.0',
                'branchName' => 'refleet/change-'.$claimed['ownerTargetId'],
                'mergeRequestUrl' => \sprintf('https://gitlab.com/backend-team/service-%d/-/merge_requests/1', $i),
                'mergeRequestIid' => '1',
            ], $runnerAuthHeader);
            Assert::assertSame(200, $client->getResponse()->getStatusCode());
        }

        $this->drainCrossContextQueues();

        $client->jsonRequest('POST', '/api/runner/jobs/claim', ['runnerId' => 'runner-fleet-01'], $runnerAuthHeader);
        Assert::assertSame(204, $client->getResponse()->getStatusCode());

        // Assert: the merge request link arrived with the result, no second call needed
        $client->jsonRequest('GET', \sprintf('/api/shifts/%s/targets', $shiftId));
        $shiftTargets = $this->decode($client->getResponse())['targets'] ?? [];
        Assert::assertCount(2, $shiftTargets);
        foreach ($shiftTargets as $target) {
            Assert::assertSame('merge_request_open', $target['status'] ?? null);
            Assert::assertMatchesRegularExpression('#^https://gitlab\.com/backend-team/service-\d/-/merge_requests/1$#', (string) ($target['mergeRequestUrl'] ?? ''));
        }

        // Act: report each target's merge request as merged
        foreach ($shiftTargets as $target) {
            $client->jsonRequest('POST', \sprintf('/api/shifts/%s/targets/%s/merge-request', $shiftId, $target['id']), [
                'status' => 'merged',
            ], $runnerAuthHeader);
            Assert::assertSame(200, $client->getResponse()->getStatusCode());
        }

        // Assert: the whole shift is now COMPLETED
        $client->jsonRequest('GET', '/api/shifts/'.$shiftId);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        Assert::assertSame('completed', $this->decode($client->getResponse())['status'] ?? null);
    }

    #[Test]
    public function mode_2_shifts_an_explicit_subset_of_qualification_targets_regardless_of_status(): void
    {
        // Arrange: architect session, three projects
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $this->drainCrossContextQueues();
        $this->authenticateAs($client, $account);

        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        $projectA = $this->registerProject($client, 'Payments Service', '1', 'backend-team/payments-service');
        $projectB = $this->registerProject($client, 'Notifications Service', '2', 'backend-team/notifications-service');
        $projectC = $this->registerProject($client, 'Billing Service', '3', 'backend-team/billing-service');

        $client->jsonRequest('POST', '/api/identity/api-keys', ['name' => 'CI runner fleet']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $runnerAuthHeader = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->decode($client->getResponse())['token']];

        // Arrange: a completed qualification with MIXED outcomes — A qualified, B and C not
        $qualificationId = $this->createAndStartQualification($client, [$projectA, $projectB, $projectC]);
        $this->claimAndReportQualificationJob($client, $runnerAuthHeader, true);
        $this->claimAndReportQualificationJob($client, $runnerAuthHeader, false);
        $this->claimAndReportQualificationJob($client, $runnerAuthHeader, false);

        $client->jsonRequest('GET', \sprintf('/api/qualifications/%s/targets', $qualificationId));
        $qualificationTargets = $this->decode($client->getResponse())['targets'] ?? [];
        $statusByProject = [];
        foreach ($qualificationTargets as $target) {
            $statusByProject[$target['projectId']] = $target['status'];
        }

        Assert::assertSame('qualified', $statusByProject[$projectA] ?? null);
        Assert::assertSame('not_qualified', $statusByProject[$projectB] ?? null);
        Assert::assertSame('not_qualified', $statusByProject[$projectC] ?? null);

        // Act: mode 2 — qualificationId + an explicit projectIds subset that deliberately
        // includes one NOT-qualified project (B), proving the override
        $client->jsonRequest('POST', '/api/shifts', [
            'title' => 'Bump acme/legacy-lib to v3',
            'qualificationId' => $qualificationId,
            'projectIds' => [$projectA, $projectB],
        ]);

        // Assert: exactly those two targets appear, regardless of their qualification status
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $createdShift = $this->decode($client->getResponse());
        Assert::assertSame(2, $createdShift['targetCount']);

        $client->jsonRequest('GET', \sprintf('/api/shifts/%s/targets', $createdShift['id']));
        $shiftProjectIds = \array_column($this->decode($client->getResponse())['targets'] ?? [], 'projectId');
        Assert::assertCount(2, $shiftProjectIds);
        Assert::assertContains($projectA, $shiftProjectIds);
        Assert::assertContains($projectB, $shiftProjectIds);
        Assert::assertNotContains($projectC, $shiftProjectIds);
    }

    #[Test]
    public function mode_3_shifts_a_fully_manual_project_selection_without_any_qualification(): void
    {
        // Arrange
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $this->drainCrossContextQueues();
        $this->authenticateAs($client, $account);

        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        $projectA = $this->registerProject($client, 'Payments Service', '1', 'backend-team/payments-service');
        $projectB = $this->registerProject($client, 'Notifications Service', '2', 'backend-team/notifications-service');

        // Act: mode 3 — no qualificationId, explicit projectIds, no qualification involved at all
        $client->jsonRequest('POST', '/api/shifts', [
            'title' => 'Roll out a manually-scoped change',
            'projectIds' => [$projectA, $projectB],
        ]);

        // Assert
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $created = $this->decode($client->getResponse());
        Assert::assertNull($created['qualificationId']);
        Assert::assertSame(2, $created['targetCount']);

        $client->jsonRequest('GET', \sprintf('/api/shifts/%s/targets', $created['id']));
        $shiftProjectIds = \array_column($this->decode($client->getResponse())['targets'] ?? [], 'projectId');
        Assert::assertCount(2, $shiftProjectIds);
        Assert::assertContains($projectA, $shiftProjectIds);
        Assert::assertContains($projectB, $shiftProjectIds);
    }

    #[Test]
    public function rejects_a_shift_with_neither_a_qualification_nor_explicit_project_ids(): void
    {
        // Arrange
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $this->drainCrossContextQueues();
        $this->authenticateAs($client, $account);

        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        // Act: neither qualificationId nor projectIds given
        $client->jsonRequest('POST', '/api/shifts', [
            'title' => 'Bump acme/legacy-lib to v3',
        ]);

        // Assert
        Assert::assertSame(422, $client->getResponse()->getStatusCode());
    }

    /**
     * @param string[] $projectIds
     */
    private function createAndStartQualification(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, array $projectIds): string
    {
        $client->jsonRequest('POST', '/api/qualifications', [
            'title' => 'Find projects depending on acme/legacy-lib',
            'qualificationMode' => 'ai',
            'qualificationPrompt' => 'Does this repository depend on acme/legacy-lib?',
            'projectIds' => $projectIds,
        ]);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $qualificationId = $this->decode($client->getResponse())['id'];

        $client->jsonRequest('POST', \sprintf('/api/qualifications/%s/start', $qualificationId));
        $this->drainCrossContextQueues();
        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        return $qualificationId;
    }

    /**
     * @param array<string, string> $runnerAuthHeader
     */
    private function claimAndReportQualificationJob(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, array $runnerAuthHeader, bool $qualified): void
    {
        $client->jsonRequest('POST', '/api/runner/jobs/claim', ['runnerId' => 'runner-fleet-01'], $runnerAuthHeader);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $this->drainCrossContextQueues();
        $claimed = $this->decode($client->getResponse());
        Assert::assertSame('qualification', $claimed['kind'] ?? null);

        $client->jsonRequest('POST', \sprintf('/api/runner/jobs/%s/report', $claimed['jobId']), [
            'runnerId' => 'runner-fleet-01',
            'outcome' => 'success',
            'summary' => $qualified ? 'Project depends on acme/legacy-lib' : 'No reference found',
            'score' => $qualified ? 5 : 1,
        ], $runnerAuthHeader);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $this->drainCrossContextQueues();
    }

    private function registerProject(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $name, string $externalId, string $path): string
    {
        $client->jsonRequest('POST', '/api/projects', [
            'name' => $name,
            'externalId' => $externalId,
            'path' => $path,
            'defaultBranch' => 'main',
        ]);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        return $this->decode($client->getResponse())['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\Symfony\Component\HttpFoundation\Response $response): array
    {
        return \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * `loginUser()` only survives the single request made immediately after it: this app
     * runs its API firewall stateless, so the security token storage is reset before every
     * subsequent request regardless of session support. A real, self-contained JWT sent as
     * a Bearer header survives that reset the same way a real client's would.
     */
    private function authenticateAs(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, \App\Identity\Account\Domain\Account\Model\Account $account): void
    {
        $token = self::getContainer()->get(TokenGenerator::class)->generate($account);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
    }
}
