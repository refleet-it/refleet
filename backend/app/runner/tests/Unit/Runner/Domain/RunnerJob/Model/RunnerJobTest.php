<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Domain\RunnerJob\Model;

use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobKindEnum;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobStatusEnum;
use App\Runner\Runner\Domain\RunnerJob\Exception\InvalidRunnerJobStateTransitionException;
use App\Runner\Runner\Domain\RunnerJob\Model\RunnerJob;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerTargetId;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RunnerJob::class)]
final class RunnerJobTest extends TestCase
{
    #[Test]
    public function enqueues_as_pending(): void
    {
        $id = RunnerJobId::generate();
        $ownerId = RunnerJobOwnerId::generate();
        $ownerTargetId = RunnerJobOwnerTargetId::generate();
        $organizationId = OrganizationId::generate();
        $payload = ['mode' => 'ai', 'prompt' => 'Does this repository depend on acme/legacy-lib?'];

        $job = RunnerJob::enqueue(
            id: $id,
            ownerId: $ownerId,
            ownerTargetId: $ownerTargetId,
            organizationId: $organizationId,
            kind: RunnerJobKindEnum::QUALIFICATION,
            ownerLabel: 'Bump acme/legacy-lib',
            mode: CriteriaModeEnum::AI,
            payload: $payload,
        );

        Assert::assertTrue($job->id()->equals($id));
        Assert::assertTrue($job->ownerId()->equals($ownerId));
        Assert::assertTrue($job->ownerTargetId()->equals($ownerTargetId));
        Assert::assertTrue($job->organizationId()->equals($organizationId));
        Assert::assertSame(RunnerJobKindEnum::QUALIFICATION, $job->kind());
        Assert::assertSame('Bump acme/legacy-lib', $job->ownerLabel());
        Assert::assertSame(CriteriaModeEnum::AI, $job->mode());
        Assert::assertSame($payload, $job->payload());
        Assert::assertNull($job->engine());
        Assert::assertSame(RunnerJobStatusEnum::PENDING, $job->status());
        Assert::assertSame(0, $job->attemptCount());
        Assert::assertNull($job->claimedBy());
        Assert::assertNull($job->completedAt());
    }

    #[Test]
    public function enqueues_with_an_engine(): void
    {
        $job = RunnerJob::enqueue(
            id: RunnerJobId::generate(),
            ownerId: RunnerJobOwnerId::generate(),
            ownerTargetId: RunnerJobOwnerTargetId::generate(),
            organizationId: OrganizationId::generate(),
            kind: RunnerJobKindEnum::CHANGE,
            ownerLabel: 'Bump acme/legacy-lib',
            mode: CriteriaModeEnum::AI,
            payload: ['mode' => 'ai', 'engine' => 'kiro'],
            engine: CriteriaEngineEnum::KIRO,
        );

        Assert::assertSame(CriteriaEngineEnum::KIRO, $job->engine());
    }

    #[Test]
    public function claims_a_pending_job(): void
    {
        $job = $this->newJob();

        $job->claim('runner-1', 300);

        Assert::assertSame(RunnerJobStatusEnum::CLAIMED, $job->status());
        Assert::assertSame('runner-1', $job->claimedBy());
        Assert::assertNotNull($job->claimedAt());
        Assert::assertNotNull($job->leaseExpiresAt());
        Assert::assertSame(1, $job->attemptCount());
    }

    #[Test]
    public function reclaims_a_job_whose_lease_has_expired(): void
    {
        $job = $this->newJob();
        $job->claim('runner-1', -10); // already-expired lease

        $job->claim('runner-2', 300);

        Assert::assertSame('runner-2', $job->claimedBy());
        Assert::assertSame(2, $job->attemptCount());
    }

    #[Test]
    public function cannot_claim_a_job_whose_lease_has_not_expired(): void
    {
        $job = $this->newJob();
        $job->claim('runner-1', 300);

        $this->expectException(InvalidRunnerJobStateTransitionException::class);

        $job->claim('runner-2', 300);
    }

    #[Test]
    public function reports_success(): void
    {
        $job = $this->newJob();
        $job->claim('runner-1', 300);

        $job->reportSuccess('Qualified', ['matches' => 3]);

        Assert::assertSame(RunnerJobStatusEnum::SUCCEEDED, $job->status());
        Assert::assertSame('Qualified', $job->resultSummary());
        Assert::assertSame(['matches' => 3], $job->resultDetails());
        Assert::assertNotNull($job->completedAt());
    }

    #[Test]
    public function reports_failure(): void
    {
        $job = $this->newJob();
        $job->claim('runner-1', 300);

        $job->reportFailure('Clone failed');

        Assert::assertSame(RunnerJobStatusEnum::FAILED, $job->status());
        Assert::assertSame('Clone failed', $job->errorMessage());
        Assert::assertNotNull($job->completedAt());
    }

    #[Test]
    public function cannot_report_success_before_being_claimed(): void
    {
        $job = $this->newJob();

        $this->expectException(InvalidRunnerJobStateTransitionException::class);

        $job->reportSuccess('too early');
    }

    #[Test]
    public function cannot_report_twice(): void
    {
        $job = $this->newJob();
        $job->claim('runner-1', 300);
        $job->reportSuccess('done');

        $this->expectException(InvalidRunnerJobStateTransitionException::class);

        $job->reportFailure('should not happen');
    }

    #[Test]
    public function cancels_a_pending_job(): void
    {
        $job = $this->newJob();

        $job->cancel();

        Assert::assertSame(RunnerJobStatusEnum::CANCELLED, $job->status());
        Assert::assertNotNull($job->completedAt());
    }

    #[Test]
    public function cancels_a_claimed_job(): void
    {
        $job = $this->newJob();
        $job->claim('runner-1', 300);

        $job->cancel();

        Assert::assertSame(RunnerJobStatusEnum::CANCELLED, $job->status());
    }

    #[Test]
    public function cannot_cancel_a_succeeded_job(): void
    {
        $job = $this->newJob();
        $job->claim('runner-1', 300);
        $job->reportSuccess('done');

        $this->expectException(InvalidRunnerJobStateTransitionException::class);

        $job->cancel();
    }

    #[Test]
    public function cannot_cancel_a_failed_job(): void
    {
        $job = $this->newJob();
        $job->claim('runner-1', 300);
        $job->reportFailure('boom');

        $this->expectException(InvalidRunnerJobStateTransitionException::class);

        $job->cancel();
    }

    #[Test]
    public function cannot_cancel_an_already_cancelled_job(): void
    {
        $job = $this->newJob();
        $job->cancel();

        $this->expectException(InvalidRunnerJobStateTransitionException::class);

        $job->cancel();
    }

    private function newJob(): RunnerJob
    {
        return RunnerJob::enqueue(
            id: RunnerJobId::generate(),
            ownerId: RunnerJobOwnerId::generate(),
            ownerTargetId: RunnerJobOwnerTargetId::generate(),
            organizationId: OrganizationId::generate(),
            kind: RunnerJobKindEnum::CHANGE,
            ownerLabel: 'Apply the migration',
            mode: CriteriaModeEnum::AI,
            payload: ['prompt' => 'Apply the migration'],
        );
    }
}
