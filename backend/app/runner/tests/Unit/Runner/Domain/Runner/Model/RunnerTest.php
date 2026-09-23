<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Domain\Runner\Model;

use App\Runner\Runner\Domain\Runner\Enum\RunnerStatusEnum;
use App\Runner\Runner\Domain\Runner\Exception\RunnerAlreadyArchivedException;
use App\Runner\Runner\Domain\Runner\Model\Runner;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Runner::class)]
final class RunnerTest extends TestCase
{
    #[Test]
    public function a_recently_seen_runner_with_no_claimed_job_is_idle(): void
    {
        $now = new \DateTimeImmutable();
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: OrganizationId::generate(),
            name: 'fleet-runner-01',
            status: RunnerStatusEnum::IDLE,
            lastSeenAt: $now->modify('-10 seconds'),
        );

        Assert::assertSame(RunnerStatusEnum::IDLE, $runner->effectiveStatus($now, hasClaimedJob: false));
    }

    #[Test]
    public function a_recently_seen_runner_with_a_claimed_job_is_working(): void
    {
        $now = new \DateTimeImmutable();
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: OrganizationId::generate(),
            name: 'fleet-runner-01',
            status: RunnerStatusEnum::IDLE,
            lastSeenAt: $now->modify('-10 seconds'),
        );

        Assert::assertSame(RunnerStatusEnum::WORKING, $runner->effectiveStatus($now, hasClaimedJob: true));
    }

    #[Test]
    public function a_runner_that_stopped_heartbeating_is_reported_as_offline_even_with_a_claimed_job(): void
    {
        $now = new \DateTimeImmutable();
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: OrganizationId::generate(),
            name: 'fleet-runner-01',
            status: RunnerStatusEnum::IDLE,
            lastSeenAt: $now->modify('-91 seconds'),
        );

        Assert::assertSame(RunnerStatusEnum::OFFLINE, $runner->effectiveStatus($now, hasClaimedJob: true));
    }

    #[Test]
    public function a_runner_that_never_heartbeat_is_offline(): void
    {
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: OrganizationId::generate(),
            name: 'fleet-runner-01',
        );

        Assert::assertSame(RunnerStatusEnum::OFFLINE, $runner->effectiveStatus(new \DateTimeImmutable(), hasClaimedJob: false));
    }

    #[Test]
    public function an_already_offline_runner_stays_offline_even_if_it_was_just_seen(): void
    {
        $now = new \DateTimeImmutable();
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: OrganizationId::generate(),
            name: 'fleet-runner-01',
            status: RunnerStatusEnum::OFFLINE,
            lastSeenAt: $now,
        );

        Assert::assertSame(RunnerStatusEnum::OFFLINE, $runner->effectiveStatus($now, hasClaimedJob: false));
    }

    #[Test]
    public function heartbeat_marks_the_runner_idle_and_records_the_seen_time(): void
    {
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: OrganizationId::generate(),
            name: 'fleet-runner-01',
        );

        $seenAt = new \DateTimeImmutable('2026-08-01 10:00:00');
        $runner->heartbeat($seenAt);

        Assert::assertSame(RunnerStatusEnum::IDLE, $runner->status());
        Assert::assertSame($seenAt, $runner->lastSeenAt());
    }

    #[Test]
    public function heartbeat_records_the_api_key_the_runner_authenticated_with(): void
    {
        $runner = $this->runner();

        $runner->heartbeat(new \DateTimeImmutable(), 'current-key');

        Assert::assertSame('current-key', $runner->apiKeyId());
    }

    #[Test]
    public function a_heartbeat_without_an_api_key_keeps_the_recorded_one(): void
    {
        $runner = $this->runner('key-1');

        $runner->heartbeat(new \DateTimeImmutable(), null);

        Assert::assertSame('key-1', $runner->apiKeyId());
    }

    #[Test]
    public function heartbeat_records_the_supported_engines_the_runner_reports(): void
    {
        $runner = $this->runner();

        $runner->heartbeat(new \DateTimeImmutable(), null, ['claude']);

        Assert::assertSame(['claude'], $runner->supportedEngines());
    }

    #[Test]
    public function a_heartbeat_without_supported_engines_keeps_the_recorded_ones(): void
    {
        $runner = $this->runner();
        $runner->heartbeat(new \DateTimeImmutable(), null, ['claude', 'kiro']);

        $runner->heartbeat(new \DateTimeImmutable(), null);

        Assert::assertSame(['claude', 'kiro'], $runner->supportedEngines());
    }

    #[Test]
    public function heartbeat_records_the_supported_models_the_runner_reports(): void
    {
        $runner = $this->runner();

        $runner->heartbeat(new \DateTimeImmutable(), null, ['claude'], ['claude-sonnet-5']);

        Assert::assertSame(['claude-sonnet-5'], $runner->supportedModels());
    }

    #[Test]
    public function a_heartbeat_without_supported_models_keeps_the_recorded_ones(): void
    {
        $runner = $this->runner();
        $runner->heartbeat(new \DateTimeImmutable(), null, ['claude'], ['claude-sonnet-5', 'claude-opus-5']);

        $runner->heartbeat(new \DateTimeImmutable(), null);

        Assert::assertSame(['claude-sonnet-5', 'claude-opus-5'], $runner->supportedModels());
    }

    #[Test]
    public function a_heartbeat_records_the_usage_the_last_agent_run_reported(): void
    {
        $runner = $this->runner();
        $usage = ['observedAt' => '2026-09-21T10:00:00.000Z', 'rateLimits' => [['window' => 'five_hour', 'status' => 'allowed', 'utilization' => 0.42, 'resetsAt' => null]], 'context' => null, 'tokens' => null, 'cost' => null];

        $runner->heartbeat(new \DateTimeImmutable(), null, null, null, $usage);

        Assert::assertSame($usage, $runner->usage());
    }

    #[Test]
    public function a_heartbeat_without_usage_keeps_the_last_reported_figures(): void
    {
        $runner = $this->runner();
        $usage = ['observedAt' => '2026-09-21T10:00:00.000Z', 'rateLimits' => null, 'context' => ['used' => 1, 'size' => 2], 'tokens' => null, 'cost' => null];
        $runner->heartbeat(new \DateTimeImmutable(), null, null, null, $usage);

        $runner->heartbeat(new \DateTimeImmutable(), null);

        Assert::assertSame($usage, $runner->usage());
    }

    #[Test]
    public function archiving_records_the_moment_and_takes_the_runner_offline(): void
    {
        $runner = $this->runner('key-1');
        $at = new \DateTimeImmutable('2026-08-08 12:00:00');

        $runner->archive($at);

        Assert::assertTrue($runner->isArchived());
        Assert::assertSame($at, $runner->archivedAt());
        Assert::assertSame(RunnerStatusEnum::OFFLINE, $runner->status());
    }

    #[Test]
    public function a_runner_can_only_be_archived_once(): void
    {
        $runner = $this->runner('key-1');
        $runner->archive(new \DateTimeImmutable());

        $this->expectException(RunnerAlreadyArchivedException::class);

        $runner->archive(new \DateTimeImmutable());
    }

    #[Test]
    public function an_archived_runner_reports_offline_regardless_of_claimed_jobs(): void
    {
        $runner = $this->runner('key-1');
        $runner->heartbeat(new \DateTimeImmutable(), 'key-1');
        $runner->archive(new \DateTimeImmutable());

        Assert::assertSame(
            RunnerStatusEnum::OFFLINE,
            $runner->effectiveStatus(new \DateTimeImmutable(), hasClaimedJob: true),
        );
    }

    #[Test]
    public function is_behind_only_when_both_versions_parse_and_its_own_is_numerically_older(): void
    {
        $runner = Runner::register(id: RunnerId::generate(), organizationId: OrganizationId::generate(), name: 'fleet-runner-01');

        Assert::assertFalse($runner->isBehind('0.1.200'));

        $runner->heartbeat(new \DateTimeImmutable(), version: '0.1.9');

        Assert::assertTrue($runner->isBehind('0.1.10'));
        Assert::assertTrue($runner->isBehind('0.2.0'));
        Assert::assertFalse($runner->isBehind('0.1.9'));
        Assert::assertFalse($runner->isBehind('0.1.8'));
        Assert::assertFalse($runner->isBehind(null));
        Assert::assertFalse($runner->isBehind('latest'));
    }

    #[Test]
    public function an_update_request_is_consumed_once(): void
    {
        $runner = Runner::register(id: RunnerId::generate(), organizationId: OrganizationId::generate(), name: 'fleet-runner-01');

        Assert::assertFalse($runner->consumeUpdateRequest());

        $runner->requestUpdate(new \DateTimeImmutable());

        Assert::assertNotNull($runner->updateRequestedAt());
        Assert::assertTrue($runner->consumeUpdateRequest());
        Assert::assertNull($runner->updateRequestedAt());
        Assert::assertFalse($runner->consumeUpdateRequest());
    }

    #[Test]
    public function an_archived_runner_cannot_be_asked_to_update(): void
    {
        $runner = Runner::register(id: RunnerId::generate(), organizationId: OrganizationId::generate(), name: 'fleet-runner-01');
        $runner->archive(new \DateTimeImmutable());

        $this->expectException(RunnerAlreadyArchivedException::class);

        $runner->requestUpdate(new \DateTimeImmutable());
    }

    private function runner(?string $apiKeyId = null): Runner
    {
        return Runner::register(
            id: RunnerId::generate(),
            organizationId: OrganizationId::generate(),
            name: 'fleet-runner-01',
            apiKeyId: $apiKeyId,
        );
    }
}
