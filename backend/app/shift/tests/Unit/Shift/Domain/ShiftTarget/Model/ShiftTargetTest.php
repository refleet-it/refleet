<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Domain\ShiftTarget\Model;

use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\MergeRequestStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Exception\InvalidShiftTargetStateTransitionException;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ProjectId;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShiftTarget::class)]
final class ShiftTargetTest extends TestCase
{
    #[Test]
    public function creates_with_pending_change_status(): void
    {
        $id = ShiftTargetId::generate();
        $shiftId = ShiftId::generate();
        $organizationId = OrganizationId::generate();
        $projectId = ProjectId::generate();

        $target = ShiftTarget::create(
            id: $id,
            shiftId: $shiftId,
            organizationId: $organizationId,
            projectId: $projectId,
            snapshot: new ProjectSnapshot('48210942', 'backend-team/payments-service', 'Payments Service', 'main'),
        );

        Assert::assertTrue($target->id()->equals($id));
        Assert::assertTrue($target->shiftId()->equals($shiftId));
        Assert::assertTrue($target->organizationId()->equals($organizationId));
        Assert::assertTrue($target->projectId()->equals($projectId));
        Assert::assertSame(ShiftTargetStatusEnum::PENDING_CHANGE, $target->status());
        Assert::assertSame(MergeRequestStatusEnum::NONE, $target->mergeRequestStatus());
        Assert::assertFalse($target->isTerminal());

        $snapshot = $target->projectSnapshot();
        Assert::assertSame('48210942', $snapshot->externalId());
        Assert::assertSame('backend-team/payments-service', $snapshot->path());
        Assert::assertSame('Payments Service', $snapshot->name());
        Assert::assertSame('main', $snapshot->defaultBranch());
    }

    #[Test]
    public function walks_the_change_and_merged_happy_path(): void
    {
        $target = $this->newTarget();

        $target->startChange('job-1');
        Assert::assertSame(ShiftTargetStatusEnum::CHANGE_IN_PROGRESS, $target->status());
        Assert::assertFalse($target->isTerminal());

        $target->recordChangeSuccess('Bumped to v2', 'shift/bump-lib', 'runner-1', 'https://gitlab.example.com/mr/1', '1');
        Assert::assertSame(ShiftTargetStatusEnum::MERGE_REQUEST_OPEN, $target->status());
        Assert::assertSame(MergeRequestStatusEnum::OPEN, $target->mergeRequestStatus());
        Assert::assertSame('Bumped to v2', $target->changeSummary());
        Assert::assertSame('shift/bump-lib', $target->changeBranchName());
        Assert::assertSame('runner-1', $target->runnerName());
        Assert::assertSame('https://gitlab.example.com/mr/1', $target->mergeRequestUrl());
        Assert::assertSame('1', $target->mergeRequestExternalIid());
        Assert::assertNotNull($target->changeCompletedAt());
        Assert::assertNotNull($target->mergeRequestOpenedAt());
        Assert::assertFalse($target->isTerminal());

        $target->recordMergeRequestMerged();
        Assert::assertSame(ShiftTargetStatusEnum::COMPLETED, $target->status());
        Assert::assertSame(MergeRequestStatusEnum::MERGED, $target->mergeRequestStatus());
        Assert::assertNotNull($target->mergeRequestMergedAt());
        Assert::assertTrue($target->isTerminal());
    }

    #[Test]
    public function a_success_without_a_merge_request_means_nothing_needed_changing(): void
    {
        $target = $this->newTarget();
        $target->startChange('job-1');

        $target->recordChangeSuccess('Already on v2', null, 'runner-1');

        Assert::assertSame(ShiftTargetStatusEnum::NO_CHANGES, $target->status());
        Assert::assertSame(MergeRequestStatusEnum::NONE, $target->mergeRequestStatus());
        Assert::assertNull($target->mergeRequestUrl());
        Assert::assertSame('Already on v2', $target->changeSummary());
        Assert::assertTrue($target->isTerminal());
    }

    #[Test]
    public function a_rerun_keeps_the_open_merge_request_and_takes_a_fresh_job(): void
    {
        $target = $this->mergeRequestOpenTarget();
        $firstOpenedAt = $target->mergeRequestOpenedAt();

        $target->rerunChange('job-2');

        Assert::assertSame(ShiftTargetStatusEnum::CHANGE_IN_PROGRESS, $target->status());
        Assert::assertSame('job-2', $target->runnerJobId());
        Assert::assertNull($target->changeSummary());
        Assert::assertNull($target->changeCompletedAt());
        Assert::assertSame('https://gitlab.example.com/mr/1', $target->mergeRequestUrl());
        Assert::assertSame(MergeRequestStatusEnum::OPEN, $target->mergeRequestStatus());

        $target->recordChangeSuccess('Bumped again', 'branch-a', 'runner-2', 'https://gitlab.example.com/mr/1', '1');

        Assert::assertSame(ShiftTargetStatusEnum::MERGE_REQUEST_OPEN, $target->status());
        Assert::assertSame($firstOpenedAt, $target->mergeRequestOpenedAt());
    }

    #[Test]
    public function a_failed_or_settled_target_can_be_rerun_but_a_merged_one_cannot(): void
    {
        $failed = $this->newTarget();
        $failed->startChange('job-1');
        $failed->recordChangeFailure('boom', 'runner-1');
        $failed->rerunChange('job-2');
        Assert::assertSame(ShiftTargetStatusEnum::CHANGE_IN_PROGRESS, $failed->status());

        $noChanges = $this->newTarget();
        $noChanges->startChange('job-1');
        $noChanges->recordChangeSuccess('nothing', null, 'runner-1');
        $noChanges->rerunChange('job-2');
        Assert::assertSame(ShiftTargetStatusEnum::CHANGE_IN_PROGRESS, $noChanges->status());

        $merged = $this->mergeRequestOpenTarget();
        $merged->recordMergeRequestMerged();

        $this->expectException(InvalidShiftTargetStateTransitionException::class);

        $merged->rerunChange('job-2');
    }

    #[Test]
    public function cannot_rerun_a_target_that_never_ran(): void
    {
        $target = $this->newTarget();

        $this->expectException(InvalidShiftTargetStateTransitionException::class);

        $target->rerunChange('job-1');
    }

    #[Test]
    public function cannot_start_change_twice(): void
    {
        $target = $this->newTarget();
        $target->startChange('job-1');

        $this->expectException(InvalidShiftTargetStateTransitionException::class);

        $target->startChange('job-2');
    }

    #[Test]
    public function records_change_failure(): void
    {
        $target = $this->newTarget();
        $target->startChange('job-1');

        $target->recordChangeFailure('Merge conflict', 'runner-1');

        Assert::assertSame(ShiftTargetStatusEnum::CHANGE_FAILED, $target->status());
        Assert::assertSame('Merge conflict', $target->changeSummary());
        Assert::assertSame('runner-1', $target->runnerName());
        Assert::assertTrue($target->isTerminal());
    }

    #[Test]
    public function cannot_record_change_success_before_change_started(): void
    {
        $target = $this->newTarget();

        $this->expectException(InvalidShiftTargetStateTransitionException::class);

        $target->recordChangeSuccess('Bumped', 'branch', 'runner-1');
    }

    #[Test]
    public function records_merge_request_closed_without_merging(): void
    {
        $target = $this->mergeRequestOpenTarget();

        $target->recordMergeRequestClosed();

        Assert::assertSame(ShiftTargetStatusEnum::MERGE_REQUEST_CLOSED, $target->status());
        Assert::assertSame(MergeRequestStatusEnum::CLOSED, $target->mergeRequestStatus());
        Assert::assertNotNull($target->mergeRequestClosedAt());
        Assert::assertTrue($target->isTerminal());
    }

    #[Test]
    public function recording_merge_request_opened_twice_is_idempotent(): void
    {
        $target = $this->mergeRequestOpenTarget();

        $target->recordMergeRequestOpened('https://gitlab.example.com/mr/1', '1');

        $openedAt = $target->mergeRequestOpenedAt();
        $target->recordMergeRequestOpened(null, null);

        Assert::assertSame(ShiftTargetStatusEnum::MERGE_REQUEST_OPEN, $target->status());
        Assert::assertSame('https://gitlab.example.com/mr/1', $target->mergeRequestUrl());
        Assert::assertSame('1', $target->mergeRequestExternalIid());
        Assert::assertEquals($openedAt, $target->mergeRequestOpenedAt());
    }

    #[Test]
    public function cannot_record_merge_request_merged_before_it_is_open(): void
    {
        $target = $this->newTarget();

        $this->expectException(InvalidShiftTargetStateTransitionException::class);

        $target->recordMergeRequestMerged();
    }

    #[Test]
    public function cancels_a_non_terminal_target(): void
    {
        $target = $this->newTarget();

        $target->cancel();

        Assert::assertSame(ShiftTargetStatusEnum::CANCELLED, $target->status());
        Assert::assertTrue($target->isTerminal());
    }

    #[Test]
    public function cancels_a_target_mid_change(): void
    {
        $target = $this->newTarget();
        $target->startChange('job-1');

        $target->cancel();

        Assert::assertSame(ShiftTargetStatusEnum::CANCELLED, $target->status());
    }

    #[Test]
    public function cannot_cancel_a_terminal_target(): void
    {
        $target = $this->newTarget();
        $target->startChange('job-1');
        $target->recordChangeFailure('boom', 'runner-1');

        $this->expectException(InvalidShiftTargetStateTransitionException::class);

        $target->cancel();
    }

    private function newTarget(): ShiftTarget
    {
        return ShiftTarget::create(
            id: ShiftTargetId::generate(),
            shiftId: ShiftId::generate(),
            organizationId: OrganizationId::generate(),
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );
    }

    private function mergeRequestOpenTarget(): ShiftTarget
    {
        $target = $this->newTarget();
        $target->startChange('job-1');
        $target->recordChangeSuccess('Bumped', 'branch-a', 'runner-1', 'https://gitlab.example.com/mr/1', '1');

        return $target;
    }
}
