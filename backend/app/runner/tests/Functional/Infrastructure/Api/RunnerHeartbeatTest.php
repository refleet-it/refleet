<?php

declare(strict_types=1);

namespace App\Tests\Functional\Runner\Infrastructure\Api;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Tests\Helpers\Messenger\DrainsCrossContextQueues;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class RunnerHeartbeatTest extends WebTestCase
{
    use DrainsCrossContextQueues;
    use Factories;
    use ResetDatabase;

    #[Test]
    public function the_usage_a_runner_reports_on_heartbeat_is_shown_on_its_detail(): void
    {
        // Arrange
        $client = self::createClient();
        [$jwt, $apiKey] = $this->organizationWithRunnerKey($client);

        $usage = [
            'observedAt' => '2026-09-21T10:00:00.000Z',
            'rateLimits' => [
                ['window' => 'five_hour', 'status' => 'allowed_warning', 'utilization' => 0.81, 'resetsAt' => '2026-09-21T12:00:00.000Z'],
                ['window' => 'seven_day', 'status' => 'allowed', 'utilization' => null, 'resetsAt' => null],
            ],
            'context' => ['used' => 42000, 'size' => 200000],
            'tokens' => ['input' => 120000, 'output' => 3200],
            'cost' => ['amount' => 0.42, 'currency' => 'USD'],
        ];

        // Act
        $client->jsonRequest('POST', '/api/runner/heartbeat', ['name' => 'box-claude', 'supportedEngines' => ['claude'], 'usage' => $usage], $apiKey);

        // Assert
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $heartbeat = $this->decode($client->getResponse());
        Assert::assertSame($usage, $heartbeat['usage']);

        $client->jsonRequest('GET', \sprintf('/api/runners/%s', $heartbeat['id']), [], $jwt);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        Assert::assertSame($usage, $this->decode($client->getResponse())['usage']);

        // A later heartbeat without usage keeps the figures the run reported.
        $client->jsonRequest('POST', '/api/runner/heartbeat', ['name' => 'box-claude'], $apiKey);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        Assert::assertSame($usage, $this->decode($client->getResponse())['usage']);
    }

    #[Test]
    public function an_update_requested_from_the_dashboard_reaches_the_runner_on_its_next_heartbeat_only_once(): void
    {
        // Arrange
        $client = self::createClient();
        [$jwt, $apiKey] = $this->organizationWithRunnerKey($client);

        $client->jsonRequest('POST', '/api/runner/heartbeat', ['name' => 'box-claude', 'version' => '0.1.186'], $apiKey);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $heartbeat = $this->decode($client->getResponse());
        Assert::assertSame('0.1.186', $heartbeat['version']);
        Assert::assertFalse($heartbeat['updateRequested']);
        Assert::assertFalse($heartbeat['updateAvailable']);

        // Act
        $client->jsonRequest('POST', \sprintf('/api/runners/%s/update', $heartbeat['id']), [], $jwt);

        // Assert
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $detail = $this->decode($client->getResponse());
        Assert::assertSame('0.1.186', $detail['version']);
        Assert::assertNotNull($detail['updateRequestedAt']);

        $client->jsonRequest('POST', '/api/runner/heartbeat', ['name' => 'box-claude', 'version' => '0.1.186'], $apiKey);
        Assert::assertTrue($this->decode($client->getResponse())['updateRequested']);

        $client->jsonRequest('POST', '/api/runner/heartbeat', ['name' => 'box-claude', 'version' => '0.1.186'], $apiKey);
        Assert::assertFalse($this->decode($client->getResponse())['updateRequested']);

        $client->jsonRequest('GET', \sprintf('/api/runners/%s', $heartbeat['id']), [], $jwt);
        Assert::assertNull($this->decode($client->getResponse())['updateRequestedAt']);
    }

    #[Test]
    public function a_malformed_usage_section_is_rejected(): void
    {
        // Arrange
        $client = self::createClient();
        [, $apiKey] = $this->organizationWithRunnerKey($client);

        // Act
        $client->jsonRequest('POST', '/api/runner/heartbeat', [
            'name' => 'box-claude',
            'usage' => [
                'observedAt' => '2026-09-21T10:00:00.000Z',
                'rateLimits' => [['window' => 'five_hour', 'status' => 'exhausted', 'utilization' => 1.5]],
            ],
        ], $apiKey);

        // Assert
        Assert::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, string>} the architect's JWT header and the runner's API-key header
     */
    private function organizationWithRunnerKey(KernelBrowser $client): array
    {
        $account = AccountFactory::createOne();
        $this->drainCrossContextQueues();

        $client->jsonRequest('POST', '/api/identity/login', ['email' => $account->email(), 'password' => 'password123']);
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        /** @var array{token: array{jwtToken: string}} $login */
        $login = $this->decode($client->getResponse());
        $jwt = ['HTTP_AUTHORIZATION' => 'Bearer '.$login['token']['jwtToken']];

        $client->jsonRequest('POST', '/api/organizations', ['name' => 'Org'], $jwt);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());

        $client->jsonRequest('POST', '/api/identity/api-keys', ['name' => 'runner'], $jwt);
        Assert::assertSame(201, $client->getResponse()->getStatusCode());
        /** @var array{token: string} $apiKey */
        $apiKey = $this->decode($client->getResponse());

        return [$jwt, ['HTTP_AUTHORIZATION' => 'Bearer '.$apiKey['token']]];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
