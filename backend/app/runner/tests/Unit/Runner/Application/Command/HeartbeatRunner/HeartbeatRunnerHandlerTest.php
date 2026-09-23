<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Application\Command\HeartbeatRunner;

use App\Runner\Runner\Application\Command\HeartbeatRunner\HeartbeatRunnerCommand;
use App\Runner\Runner\Application\Command\HeartbeatRunner\HeartbeatRunnerHandler;
use App\Runner\Runner\Domain\Runner\Enum\RunnerStatusEnum;
use App\Runner\Runner\Domain\Runner\Model\Runner;
use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\Service\LatestRunnerVersionProviderInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeartbeatRunnerHandler::class)]
final class HeartbeatRunnerHandlerTest extends TestCase
{
    private RunnerRepositoryInterface&MockObject $runners;

    private LatestRunnerVersionProviderInterface&Stub $latestVersions;

    private HeartbeatRunnerHandler $handler;

    #[Test]
    public function registers_a_new_runner_on_first_heartbeat(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $this->runners
            ->expects($this->once())
            ->method('findByOrganizationIdAndName')
            ->with($organizationId, 'runner-fleet-01')
            ->willReturn(null);

        $saved = null;
        $this->runners
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Runner $runner) use (&$saved): bool {
                $saved = $runner;

                return true;
            }));

        // Act
        $result = ($this->handler)(new HeartbeatRunnerCommand(
            organizationId: $organizationId->asString(),
            name: 'runner-fleet-01',
            apiKeyId: 'key-1',
        ));

        // Assert
        Assert::assertInstanceOf(Runner::class, $saved);
        Assert::assertSame('runner-fleet-01', $result->name);
        Assert::assertSame('idle', $result->status);
        Assert::assertSame(RunnerStatusEnum::IDLE, $saved->status());
        Assert::assertSame('key-1', $saved->apiKeyId());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function refreshes_the_api_key_the_runner_currently_authenticates_with(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01',
            apiKeyId: 'rotated-away-key',
        );

        $this->runners->method('findByOrganizationIdAndName')->willReturn($runner);

        // Act
        ($this->handler)(new HeartbeatRunnerCommand(
            organizationId: $organizationId->asString(),
            name: 'runner-fleet-01',
            apiKeyId: 'current-key',
        ));

        // Assert
        Assert::assertSame('current-key', $runner->apiKeyId());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function keeps_the_stored_api_key_when_the_caller_has_none(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01',
            apiKeyId: 'key-1',
        );

        $this->runners->method('findByOrganizationIdAndName')->willReturn($runner);

        // Act — e.g. a browser session hitting the heartbeat endpoint, which carries no API key
        ($this->handler)(new HeartbeatRunnerCommand(
            organizationId: $organizationId->asString(),
            name: 'runner-fleet-01',
            apiKeyId: null,
        ));

        // Assert
        Assert::assertSame('key-1', $runner->apiKeyId());
    }

    #[Test]
    public function registers_the_supported_engines_reported_on_first_heartbeat(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $this->runners->method('findByOrganizationIdAndName')->willReturn(null);

        $saved = null;
        $this->runners
            ->method('save')
            ->with($this->callback(static function (Runner $runner) use (&$saved): bool {
                $saved = $runner;

                return true;
            }));

        // Act
        $result = ($this->handler)(new HeartbeatRunnerCommand(
            organizationId: $organizationId->asString(),
            name: 'runner-fleet-01-claude',
            supportedEngines: ['claude'],
        ));

        // Assert
        Assert::assertSame(['claude'], $saved->supportedEngines());
        Assert::assertSame(['claude'], $result->supportedEngines);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function keeps_the_stored_supported_engines_when_a_heartbeat_carries_none(): void
    {
        // Arrange: e.g. a browser session hitting the heartbeat endpoint, which reports
        // no engines at all — must not wipe out what the real runner process last reported.
        $organizationId = OrganizationId::generate();
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01-claude',
            supportedEngines: ['claude'],
        );

        $this->runners->method('findByOrganizationIdAndName')->willReturn($runner);

        // Act
        ($this->handler)(new HeartbeatRunnerCommand(
            organizationId: $organizationId->asString(),
            name: 'runner-fleet-01-claude',
        ));

        // Assert
        Assert::assertSame(['claude'], $runner->supportedEngines());
    }

    #[Test]
    public function registers_the_supported_models_reported_on_first_heartbeat(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $this->runners->method('findByOrganizationIdAndName')->willReturn(null);

        $saved = null;
        $this->runners
            ->method('save')
            ->with($this->callback(static function (Runner $runner) use (&$saved): bool {
                $saved = $runner;

                return true;
            }));

        // Act
        $result = ($this->handler)(new HeartbeatRunnerCommand(
            organizationId: $organizationId->asString(),
            name: 'runner-fleet-01-claude',
            supportedEngines: ['claude'],
            supportedModels: ['claude-sonnet-5', 'claude-opus-5'],
        ));

        // Assert
        Assert::assertSame(['claude-sonnet-5', 'claude-opus-5'], $saved->supportedModels());
        Assert::assertSame(['claude-sonnet-5', 'claude-opus-5'], $result->supportedModels);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function records_the_usage_a_heartbeat_carries_and_echoes_it_back(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01-claude',
        );
        $usage = [
            'observedAt' => '2026-09-21T10:00:00.000Z',
            'rateLimits' => [['window' => 'five_hour', 'status' => 'allowed_warning', 'utilization' => 0.81, 'resetsAt' => '2026-09-21T12:00:00.000Z']],
            'context' => ['used' => 42000, 'size' => 200000],
            'tokens' => ['input' => 120000, 'output' => 3200],
            'cost' => ['amount' => 0.42, 'currency' => 'USD'],
        ];

        $this->runners->method('findByOrganizationIdAndName')->willReturn($runner);

        // Act
        $result = ($this->handler)(new HeartbeatRunnerCommand(
            organizationId: $organizationId->asString(),
            name: 'runner-fleet-01-claude',
            usage: $usage,
        ));

        // Assert
        Assert::assertSame($usage, $runner->usage());
        Assert::assertSame($usage, $result->usage);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function keeps_the_stored_supported_models_when_a_heartbeat_carries_none(): void
    {
        // Arrange: e.g. a browser session hitting the heartbeat endpoint, which reports
        // no models at all — must not wipe out what the real runner process last reported.
        $organizationId = OrganizationId::generate();
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01-claude',
            supportedEngines: ['claude'],
            supportedModels: ['claude-sonnet-5'],
        );

        $this->runners->method('findByOrganizationIdAndName')->willReturn($runner);

        // Act
        ($this->handler)(new HeartbeatRunnerCommand(
            organizationId: $organizationId->asString(),
            name: 'runner-fleet-01-claude',
        ));

        // Assert
        Assert::assertSame(['claude-sonnet-5'], $runner->supportedModels());
    }

    #[Test]
    public function marks_an_existing_runner_idle_and_refreshes_last_seen_at(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01',
            status: RunnerStatusEnum::OFFLINE,
            lastSeenAt: new \DateTimeImmutable('-1 hour'),
        );

        $this->runners->method('findByOrganizationIdAndName')->willReturn($runner);
        $this->runners->expects($this->once())->method('save')->with($runner);

        // Act
        $result = ($this->handler)(new HeartbeatRunnerCommand(
            organizationId: $organizationId->asString(),
            name: 'runner-fleet-01',
        ));

        // Assert
        Assert::assertSame(RunnerStatusEnum::IDLE, $runner->status());
        Assert::assertSame($runner->id()->asString(), $result->id);
        Assert::assertGreaterThan(new \DateTimeImmutable('-1 minute'), $runner->lastSeenAt());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function tells_the_runner_where_it_stands_against_the_published_version(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01-claude',
        );

        $this->runners->method('findByOrganizationIdAndName')->willReturn($runner);
        $this->latestVersions->method('latestVersion')->willReturn('0.1.200');

        // Act
        $result = ($this->handler)(new HeartbeatRunnerCommand(
            organizationId: $organizationId->asString(),
            name: 'runner-fleet-01-claude',
            version: '0.1.186',
        ));

        // Assert
        Assert::assertSame('0.1.186', $runner->version());
        Assert::assertSame('0.1.186', $result->version);
        Assert::assertSame('0.1.200', $result->latestVersion);
        Assert::assertTrue($result->updateAvailable);
        Assert::assertFalse($result->updateRequested);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function hands_a_pending_update_request_to_the_runner_exactly_once(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01-claude',
        );
        $runner->requestUpdate(new \DateTimeImmutable());

        $this->runners->method('findByOrganizationIdAndName')->willReturn($runner);

        // Act — a browser session (no version) must not swallow the request meant for the process
        $browser = ($this->handler)(new HeartbeatRunnerCommand(organizationId: $organizationId->asString(), name: 'runner-fleet-01-claude'));
        $first = ($this->handler)(new HeartbeatRunnerCommand(organizationId: $organizationId->asString(), name: 'runner-fleet-01-claude', version: '0.1.186'));
        $second = ($this->handler)(new HeartbeatRunnerCommand(organizationId: $organizationId->asString(), name: 'runner-fleet-01-claude', version: '0.1.186'));

        // Assert
        Assert::assertFalse($browser->updateRequested);
        Assert::assertTrue($first->updateRequested);
        Assert::assertFalse($second->updateRequested);
        Assert::assertNull($runner->updateRequestedAt());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->runners = $this->createMock(RunnerRepositoryInterface::class);
        $this->latestVersions = $this->createStub(LatestRunnerVersionProviderInterface::class);
        $this->handler = new HeartbeatRunnerHandler($this->runners, $this->latestVersions);
    }
}
