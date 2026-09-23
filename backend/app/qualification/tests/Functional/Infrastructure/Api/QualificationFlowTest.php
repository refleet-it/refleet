<?php

declare(strict_types=1);

namespace App\Tests\Functional\Qualification\Infrastructure\Api;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Tests\Helpers\Messenger\DrainsCrossContextQueues;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Full qualification lifecycle, driven purely through the public HTTP API: draft ->
 * running -> completed, with a runner fleet (API key) claiming and reporting the
 * qualification jobs. Qualification is now fully independent of Shift/change — it is
 * never blocked on, or coupled to, any downstream change.
 */
final class QualificationFlowTest extends WebTestCase
{
    use DrainsCrossContextQueues;
    use Factories;
    use ResetDatabase;

    #[Test]
    public function drives_a_qualification_from_draft_to_completed_with_mixed_results(): void
    {
        // Arrange: architect session with two projects
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $this->drainCrossContextQueues();
        $this->authenticateAs($client, $account);

        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        $client->jsonRequest('POST', '/api/projects', [
            'name' => 'Payments Service',
            'externalId' => '1',
            'path' => 'backend-team/payments-service',
            'defaultBranch' => 'main',
        ]);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $projectA = $this->decode($client->getResponse())['id'];

        $client->jsonRequest('POST', '/api/projects', [
            'name' => 'Notifications Service',
            'externalId' => '2',
            'path' => 'backend-team/notifications-service',
            'defaultBranch' => 'main',
        ]);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $projectB = $this->decode($client->getResponse())['id'];

        $client->jsonRequest('POST', '/api/identity/api-keys', ['name' => 'CI runner fleet']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $apiKey = $this->decode($client->getResponse());
        $runnerAuthHeader = ['HTTP_AUTHORIZATION' => 'Bearer '.$apiKey['token']];

        // Act: draft the qualification targeting both projects
        $client->jsonRequest('POST', '/api/qualifications', [
            'title' => 'Find projects depending on acme/legacy-lib',
            'qualificationMode' => 'ai',
            'qualificationPrompt' => 'Does this repository depend on acme/legacy-lib?',
            'projectIds' => [$projectA, $projectB],
        ]);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $created = $this->decode($client->getResponse());
        $qualificationId = $created['id'];
        Assert::assertSame('draft', $created['status']);
        Assert::assertSame(2, $created['targetCount']);

        // Act: start the qualification
        $client->jsonRequest('POST', \sprintf('/api/qualifications/%s/start', $qualificationId));
        $this->drainCrossContextQueues();
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        Assert::assertSame('running', $this->decode($client->getResponse())['status'] ?? null);

        // Arrange: which target belongs to which project, so the outcome can follow the
        // claimed job rather than assume the two jobs come off the queue in a given order
        $client->jsonRequest('GET', \sprintf('/api/qualifications/%s/targets', $qualificationId));
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $projectByTargetId = [];
        foreach ($this->decode($client->getResponse())['targets'] ?? [] as $target) {
            $projectByTargetId[$target['id']] = $target['projectId'];
        }

        Assert::assertCount(2, $projectByTargetId);

        // Act: runner claims and reports each target with mixed outcomes — A qualifies, B doesn't
        foreach ([1, 2] as $claimNumber) {
            $client->jsonRequest('POST', '/api/runner/jobs/claim', ['runnerId' => 'runner-fleet-01'], $runnerAuthHeader);
            Assert::assertSame(200, $client->getResponse()->getStatusCode(), \sprintf('claim #%d', $claimNumber));
            $this->drainCrossContextQueues();
            $claim = $this->decode($client->getResponse());
            Assert::assertSame('qualification', $claim['kind'] ?? null);
            $isProjectA = $projectA === ($projectByTargetId[$claim['ownerTargetId']] ?? null);

            $client->jsonRequest('POST', \sprintf('/api/runner/jobs/%s/report', $claim['jobId']), [
                'runnerId' => 'runner-fleet-01',
                'outcome' => 'success',
                'summary' => $isProjectA ? 'Project depends on acme/legacy-lib in composer.json' : 'No reference to acme/legacy-lib found',
                'score' => $isProjectA ? 5 : 2,
            ], $runnerAuthHeader);
            Assert::assertSame(200, $client->getResponse()->getStatusCode());
            $this->drainCrossContextQueues();
        }

        // Assert: no more qualification work available
        $client->jsonRequest('POST', '/api/runner/jobs/claim', ['runnerId' => 'runner-fleet-01'], $runnerAuthHeader);
        Assert::assertSame(204, $client->getResponse()->getStatusCode());

        // Assert: the qualification is completed and both mixed outcomes are reflected
        $client->jsonRequest('GET', '/api/qualifications/'.$qualificationId);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        Assert::assertSame('completed', $this->decode($client->getResponse())['status'] ?? null);

        $client->jsonRequest('GET', \sprintf('/api/qualifications/%s/targets', $qualificationId));
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $targets = $this->decode($client->getResponse())['targets'] ?? [];
        Assert::assertCount(2, $targets);

        $statusByProject = [];
        foreach ($targets as $target) {
            $statusByProject[$target['projectId']] = $target['status'];
        }

        Assert::assertSame('qualified', $statusByProject[$projectA] ?? null);
        Assert::assertSame('not_qualified', $statusByProject[$projectB] ?? null);

        // Act: manually override the not-qualified target
        $notQualifiedTargetId = null;
        foreach ($targets as $target) {
            if ($projectB === $target['projectId']) {
                $notQualifiedTargetId = $target['id'];
            }
        }

        Assert::assertNotNull($notQualifiedTargetId);

        $client->jsonRequest('POST', \sprintf('/api/qualifications/%s/targets/%s/override', $qualificationId, $notQualifiedTargetId), [
            'qualified' => true,
            'note' => 'Manually confirmed: this repo also depends on acme/legacy-lib transitively',
        ]);

        // Assert: the override sticks
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $overridden = $this->decode($client->getResponse());
        Assert::assertSame('qualified', $overridden['status'] ?? null);
        Assert::assertTrue($overridden['overridden'] ?? null);
        Assert::assertSame(
            'Manually confirmed: this repo also depends on acme/legacy-lib transitively',
            $overridden['overrideNote'] ?? null
        );
    }

    #[Test]
    public function retries_a_failed_target_and_completes_the_qualification_again(): void
    {
        // Arrange: architect session with one project and a runner key
        $client = self::createClient();
        $account = AccountFactory::createOne();
        $this->drainCrossContextQueues();
        $this->authenticateAs($client, $account);

        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Acme Inc.']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        $client->jsonRequest('POST', '/api/projects', [
            'name' => 'Payments Service',
            'externalId' => '1',
            'path' => 'backend-team/payments-service',
            'defaultBranch' => 'main',
        ]);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $projectId = $this->decode($client->getResponse())['id'];

        $client->jsonRequest('POST', '/api/identity/api-keys', ['name' => 'CI runner fleet']);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $runnerAuthHeader = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->decode($client->getResponse())['token']];

        $client->jsonRequest('POST', '/api/qualifications', [
            'title' => 'Find projects depending on acme/legacy-lib',
            'qualificationMode' => 'ai',
            'qualificationPrompt' => 'Does this repository depend on acme/legacy-lib?',
            'projectIds' => [$projectId],
        ]);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $qualificationId = $this->decode($client->getResponse())['id'];

        $client->jsonRequest('POST', \sprintf('/api/qualifications/%s/start', $qualificationId));
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $this->drainCrossContextQueues();

        // Act: the runner picks the job up and reports a failure
        $client->jsonRequest('POST', '/api/runner/jobs/claim', ['runnerId' => 'runner-fleet-01'], $runnerAuthHeader);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $this->drainCrossContextQueues();
        $firstClaim = $this->decode($client->getResponse());
        Assert::assertStringContainsString('{"score": <integer 1-5>', (string) ($firstClaim['payload']['prompt'] ?? ''));

        $client->jsonRequest('POST', \sprintf('/api/runner/jobs/%s/report', $firstClaim['jobId']), [
            'runnerId' => 'runner-fleet-01',
            'outcome' => 'failure',
            'summary' => 'Agent did not return a clear qualification decision',
            'errorMessage' => 'I would need to know which version you mean.',
        ], $runnerAuthHeader);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $this->drainCrossContextQueues();

        // Assert: the batch settled with the failure and nothing is left to claim
        $client->jsonRequest('GET', '/api/qualifications/'.$qualificationId);
        $completed = $this->decode($client->getResponse());
        Assert::assertSame('completed', $completed['status'] ?? null);
        Assert::assertSame(1, $completed['statusBreakdown']['failed'] ?? null);

        $client->jsonRequest('POST', '/api/runner/jobs/claim', ['runnerId' => 'runner-fleet-01'], $runnerAuthHeader);
        Assert::assertSame(204, $client->getResponse()->getStatusCode());

        // Act: retry every failed target
        $client->jsonRequest('POST', \sprintf('/api/qualifications/%s/retry', $qualificationId));
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $this->drainCrossContextQueues();
        $reopened = $this->decode($client->getResponse());
        Assert::assertSame('running', $reopened['status'] ?? null);
        Assert::assertSame(1, $reopened['statusBreakdown']['in_progress'] ?? null);

        // Assert: a brand-new job is claimable for the same target, and the old error is gone
        $client->jsonRequest('POST', '/api/runner/jobs/claim', ['runnerId' => 'runner-fleet-01'], $runnerAuthHeader);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $this->drainCrossContextQueues();
        $secondClaim = $this->decode($client->getResponse());
        Assert::assertNotSame($firstClaim['jobId'], $secondClaim['jobId']);
        Assert::assertSame($firstClaim['ownerTargetId'], $secondClaim['ownerTargetId']);

        $client->jsonRequest('GET', \sprintf('/api/qualifications/%s/targets', $qualificationId));
        $target = $this->decode($client->getResponse())['targets'][0] ?? [];
        Assert::assertSame('in_progress', $target['status'] ?? null);
        Assert::assertNull($target['summary'] ?? null);

        // Act: this time the agent reaches a decision
        $client->jsonRequest('POST', \sprintf('/api/runner/jobs/%s/report', $secondClaim['jobId']), [
            'runnerId' => 'runner-fleet-01',
            'outcome' => 'success',
            'summary' => 'composer.json requires acme/legacy-lib ^1.4.',
            'score' => 4,
        ], $runnerAuthHeader);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $this->drainCrossContextQueues();

        // Assert: the qualification completed again with the retried result
        $client->jsonRequest('GET', '/api/qualifications/'.$qualificationId);
        $recompleted = $this->decode($client->getResponse());
        Assert::assertSame('completed', $recompleted['status'] ?? null);
        Assert::assertSame(1, $recompleted['statusBreakdown']['qualified'] ?? null);

        // Assert: with nothing failed, retrying again is a harmless no-op
        $client->jsonRequest('POST', \sprintf('/api/qualifications/%s/retry', $qualificationId));
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        Assert::assertSame('completed', $this->decode($client->getResponse())['status'] ?? null);

        // Assert: a settled target cannot be retried one by one either
        $client->jsonRequest('POST', \sprintf('/api/qualifications/%s/targets/%s/retry', $qualificationId, $target['id']));
        Assert::assertSame(409, $client->getResponse()->getStatusCode());
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
