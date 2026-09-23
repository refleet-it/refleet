<?php

declare(strict_types=1);

namespace App\Tests\Functional\Runner\Infrastructure\Api;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Tests\Helpers\Messenger\DrainsCrossContextQueues;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class RunnerAuthenticationTest extends WebTestCase
{
    use DrainsCrossContextQueues;
    use Factories;
    use ResetDatabase;

    #[Test]
    public function claim_without_a_bearer_token_is_unauthorized(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->jsonRequest('POST', '/api/runner/jobs/claim', ['runnerId' => 'runner-fleet-01']);

        // Assert
        Assert::assertSame(401, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function report_without_a_bearer_token_is_unauthorized(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->jsonRequest('POST', '/api/runner/jobs/11111111-1111-1111-1111-111111111111/report', [
            'runnerId' => 'runner-fleet-01',
            'outcome' => 'success',
            'summary' => 'irrelevant',
        ]);

        // Assert
        Assert::assertSame(401, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function claim_with_a_garbage_bearer_token_is_unauthorized(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->jsonRequest('POST', '/api/runner/jobs/claim', ['runnerId' => 'runner-fleet-01'], [
            'HTTP_AUTHORIZATION' => 'Bearer sw_not-a-real-key',
        ]);

        // Assert
        Assert::assertSame(401, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function a_runners_api_key_only_ever_claims_jobs_from_its_own_organization(): void
    {
        // Arrange: a single client is reused for both actors - Symfony's kernel can only be
        // booted once per test. loginUser() sets up a session-bound security token, but that
        // doesn't compose well with switching identities mid-test; every request below is
        // authenticated explicitly via its own Authorization header instead (a real JWT
        // obtained through the login endpoint for the architect calls, the API key itself for
        // the runner calls), exactly like two independent real-world clients would behave.
        $client = self::createClient();

        // Arrange: organization A has a qualification with a pending runner job
        $accountA = AccountFactory::createOne();
        $this->drainCrossContextQueues();
        $jwtA = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->loginAndGetJwt($client, $accountA->email())];

        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Org A'], $jwtA);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        $client->jsonRequest('POST', '/api/projects', [
            'name' => 'Org A Service',
            'externalId' => '1',
            'path' => 'org-a/service',
        ], $jwtA);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        $client->jsonRequest('POST', '/api/qualifications', [
            'title' => 'Org A qualification',
            'qualificationMode' => 'ai',
            'qualificationPrompt' => 'Does this repository depend on acme/legacy-lib?',
        ], $jwtA);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $qualificationIdA = $this->decode($client->getResponse())['id'];

        $client->jsonRequest('POST', \sprintf('/api/qualifications/%s/start', $qualificationIdA), [], $jwtA);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        // Arrange: organization B is a completely separate account/org with its own API key
        $accountB = AccountFactory::createOne();
        $this->drainCrossContextQueues();
        $jwtB = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->loginAndGetJwt($client, $accountB->email())];

        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Org B'], $jwtB);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        $client->jsonRequest('POST', '/api/identity/api-keys', ['name' => 'Org B runner'], $jwtB);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $apiKeyB = $this->decode($client->getResponse());

        // Act: Org B's runner tries to claim work
        $client->jsonRequest('POST', '/api/runner/jobs/claim', ['runnerId' => 'org-b-runner'], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$apiKeyB['token'],
        ]);

        // Assert: Org A's job is invisible to Org B, even though a job is pending
        Assert::assertSame(204, $client->getResponse()->getStatusCode());

        // Sanity check: Org A's own runner can claim it
        $client->jsonRequest('POST', '/api/identity/api-keys', ['name' => 'Org A runner'], $jwtA);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        $apiKeyA = $this->decode($client->getResponse());

        $client->jsonRequest('POST', '/api/runner/jobs/claim', ['runnerId' => 'org-a-runner'], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$apiKeyA['token'],
        ]);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
    }

    private function loginAndGetJwt(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email): string
    {
        $client->jsonRequest('POST', '/api/identity/login', [
            'email' => $email,
            'password' => 'password123',
        ]);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());

        /** @var array{token: array{jwtToken: string}} $response */
        $response = $this->decode($client->getResponse());

        return $response['token']['jwtToken'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\Symfony\Component\HttpFoundation\Response $response): array
    {
        return \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
