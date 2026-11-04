<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Domain\ShiftTarget\Model;

use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\MergeRequestStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Enum\PipelineFixOutcomeEnum;
use App\Shift\Shift\Domain\ShiftTarget\Enum\PipelineStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Exception\InvalidShiftTargetStateTransitionException;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\MergeRequestPipeline;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ProjectId;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShiftTarget::class)]
#[CoversClass(MergeRequestPipeline::class)]
final class ShiftTargetTest extends TestCase
{
    private const string HEAD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string OTHER = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const string FIXED = 'cccccccccccccccccccccccccccccccccccccccc';

    private const string JOB = '0199a000-0000-7000-8000-000000000001';

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

    #[Test]
    public function a_shift_that_opted_in_starts_watching_the_commit_the_runner_pushed(): void
    {
        $target = $this->watched();

        Assert::assertSame(PipelineStatusEnum::WAITING, $target->pipelineStatus());
        Assert::assertSame(self::HEAD, $target->pipelineHeadSha());
    }

    #[Test]
    public function a_shift_that_opted_out_is_not_watched(): void
    {
        $target = $this->inProgress();
        $target->recordChangeSuccess('done', 'refleet/change-1', 'runner', 'https://gitlab.com/a/b/-/merge_requests/9', '9', self::HEAD, false);

        Assert::assertSame(PipelineStatusEnum::NOT_WATCHED, $target->pipelineStatus());
    }

    #[Test]
    public function a_runner_too_old_to_report_the_commit_leaves_the_merge_request_unwatched(): void
    {
        $target = $this->inProgress();
        $target->recordChangeSuccess('done', 'refleet/change-1', 'runner', 'https://gitlab.com/a/b/-/merge_requests/9', '9', null, true);

        Assert::assertSame(PipelineStatusEnum::NOT_WATCHED, $target->pipelineStatus());
    }

    #[Test]
    public function a_change_that_opened_no_merge_request_is_not_watched(): void
    {
        $target = $this->inProgress();
        $target->recordChangeSuccess('nothing to do', null, 'runner', null, null, self::HEAD, true);

        Assert::assertSame(ShiftTargetStatusEnum::NO_CHANGES, $target->status());
        Assert::assertSame(PipelineStatusEnum::NOT_WATCHED, $target->pipelineStatus());
    }

    #[Test]
    public function a_passing_pipeline_settles_the_watch(): void
    {
        $target = $this->watched();

        Assert::assertFalse($target->observePipeline($this->pipeline('success')));
        Assert::assertSame(PipelineStatusEnum::PASSED, $target->pipelineStatus());
    }

    #[Test]
    public function a_failed_pipeline_asks_for_a_fix(): void
    {
        $target = $this->watched();

        Assert::assertTrue($target->observePipeline($this->pipeline('failed')));
        Assert::assertSame(PipelineStatusEnum::WAITING, $target->pipelineStatus());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unfinishedStatuses(): iterable
    {
        foreach (['created', 'waiting_for_resource', 'preparing', 'pending', 'running', 'scheduled'] as $status) {
            yield $status => [$status];
        }
    }

    #[Test]
    #[DataProvider('unfinishedStatuses')]
    public function an_unfinished_pipeline_keeps_the_watch_going(string $status): void
    {
        $target = $this->watched();

        Assert::assertFalse($target->observePipeline($this->pipeline($status)));
        Assert::assertSame(PipelineStatusEnum::WAITING, $target->pipelineStatus());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function inconclusiveStatuses(): iterable
    {
        foreach (['canceled', 'canceling', 'skipped', 'manual'] as $status) {
            yield $status => [$status];
        }
    }

    #[Test]
    #[DataProvider('inconclusiveStatuses')]
    public function a_pipeline_that_says_nothing_either_way_is_not_fixed(string $status): void
    {
        $target = $this->watched();

        Assert::assertFalse($target->observePipeline($this->pipeline($status)));
        Assert::assertSame(PipelineStatusEnum::INCONCLUSIVE, $target->pipelineStatus());
    }

    #[Test]
    public function a_head_refleet_did_not_push_stands_it_down_even_on_a_red_pipeline(): void
    {
        $target = $this->watched();

        $observed = new MergeRequestPipeline(self::OTHER, '77', self::OTHER, 'refleet/change-1', 'failed');

        Assert::assertFalse($target->observePipeline($observed));
        Assert::assertSame(PipelineStatusEnum::STOOD_DOWN, $target->pipelineStatus());
    }

    #[Test]
    public function a_red_pipeline_from_an_earlier_commit_is_not_a_reason_to_act(): void
    {
        $target = $this->watched();

        $observed = new MergeRequestPipeline(self::HEAD, '76', self::OTHER, 'refleet/change-1', 'failed');

        Assert::assertFalse($target->observePipeline($observed));
        Assert::assertSame(PipelineStatusEnum::WAITING, $target->pipelineStatus());
    }

    #[Test]
    public function a_merged_results_pipeline_counts_although_it_runs_on_a_merge_commit(): void
    {
        $target = $this->watched();

        $observed = new MergeRequestPipeline(self::HEAD, '77', self::OTHER, 'refs/merge-requests/9/merge', 'failed');

        Assert::assertTrue($target->observePipeline($observed));
    }

    #[Test]
    public function a_merged_results_pipeline_already_sent_to_an_agent_is_not_sent_again(): void
    {
        $target = $this->watched();
        $target->startPipelineFix(self::JOB, '77');
        $target->recordPipelineFixResult(self::JOB, PipelineFixOutcomeEnum::FIXED, 'fixed', self::FIXED);

        $observed = new MergeRequestPipeline(self::FIXED, '77', self::OTHER, 'refs/merge-requests/9/merge', 'failed');

        Assert::assertFalse($target->observePipeline($observed));
        Assert::assertSame(PipelineStatusEnum::WAITING, $target->pipelineStatus());
    }

    #[Test]
    public function a_merge_request_without_a_pipeline_for_an_hour_counts_as_having_no_ci(): void
    {
        $target = $this->watched();
        $noPipeline = new MergeRequestPipeline(self::HEAD);

        Assert::assertFalse($target->observePipeline($noPipeline));
        Assert::assertSame(PipelineStatusEnum::WAITING, $target->pipelineStatus());

        $this->watchStartedAt($target, new \DateTimeImmutable('-61 minutes'));

        Assert::assertFalse($target->observePipeline($noPipeline));
        Assert::assertSame(PipelineStatusEnum::NO_PIPELINE, $target->pipelineStatus());
    }

    #[Test]
    public function a_pushed_fix_goes_back_to_watching_the_new_commit(): void
    {
        $target = $this->watched();
        $target->startPipelineFix(self::JOB, '77');
        Assert::assertSame(PipelineStatusEnum::FIXING, $target->pipelineStatus());

        $target->recordPipelineFixResult(self::JOB, PipelineFixOutcomeEnum::FIXED, 'Fixed the types.', self::FIXED);

        Assert::assertSame(PipelineStatusEnum::WAITING, $target->pipelineStatus());
        Assert::assertSame(self::FIXED, $target->pipelineHeadSha());
        Assert::assertSame('Fixed the types.', $target->pipelineSummary());
        Assert::assertNull($target->pipelineFixJobId());
    }

    /**
     * @return iterable<string, array{PipelineFixOutcomeEnum, PipelineStatusEnum}>
     */
    public static function settlingOutcomes(): iterable
    {
        yield 'unrelated' => [PipelineFixOutcomeEnum::UNRELATED, PipelineStatusEnum::UNRELATED];
        yield 'superseded' => [PipelineFixOutcomeEnum::SUPERSEDED, PipelineStatusEnum::STOOD_DOWN];
        yield 'not fixed' => [PipelineFixOutcomeEnum::NOT_FIXED, PipelineStatusEnum::GAVE_UP];
    }

    #[Test]
    #[DataProvider('settlingOutcomes')]
    public function other_outcomes_end_the_watch(PipelineFixOutcomeEnum $outcome, PipelineStatusEnum $expected): void
    {
        $target = $this->watched();
        $target->startPipelineFix(self::JOB, '77');

        $target->recordPipelineFixResult(self::JOB, $outcome, 'why');

        Assert::assertSame($expected, $target->pipelineStatus());
        Assert::assertSame(ShiftTargetStatusEnum::MERGE_REQUEST_OPEN, $target->status());
    }

    #[Test]
    public function a_failed_job_uses_up_an_attempt_and_the_same_pipeline_is_sent_again(): void
    {
        $target = $this->watched();
        Assert::assertTrue($target->observePipeline($this->pipeline('failed')));
        $target->startPipelineFix(self::JOB, '77');

        $target->recordPipelineFixResult(self::JOB, PipelineFixOutcomeEnum::JOB_FAILED, 'push rejected');

        Assert::assertSame(PipelineStatusEnum::WAITING, $target->pipelineStatus());
        Assert::assertSame(self::HEAD, $target->pipelineHeadSha());
        Assert::assertSame('push rejected', $target->pipelineSummary());
        Assert::assertNull($target->pipelineCheckedAt());
        Assert::assertSame(1, $target->pipelineFixAttempts());
        Assert::assertTrue($target->observePipeline($this->pipeline('failed')));
    }

    #[Test]
    public function failed_jobs_stop_at_the_attempt_limit_and_keep_the_last_reason(): void
    {
        $target = $this->watched();

        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            Assert::assertTrue($target->observePipeline($this->pipeline('failed')));
            $target->startPipelineFix(self::JOB, '77');
            $target->recordPipelineFixResult(self::JOB, PipelineFixOutcomeEnum::JOB_FAILED, 'push rejected '.$attempt);
        }

        Assert::assertFalse($target->observePipeline($this->pipeline('failed')));
        Assert::assertSame(PipelineStatusEnum::GAVE_UP, $target->pipelineStatus());
        Assert::assertSame('push rejected 3', $target->pipelineSummary());
    }

    #[Test]
    public function a_failed_job_after_someone_pushed_stands_down_on_the_next_look(): void
    {
        $target = $this->watched();
        $target->startPipelineFix(self::JOB, '77');
        $target->recordPipelineFixResult(self::JOB, PipelineFixOutcomeEnum::JOB_FAILED, 'rejected: fetch first');

        $observed = new MergeRequestPipeline(self::OTHER, '78', self::OTHER, 'refleet/change-1', 'failed');

        Assert::assertFalse($target->observePipeline($observed));
        Assert::assertSame(PipelineStatusEnum::STOOD_DOWN, $target->pipelineStatus());
    }

    #[Test]
    public function a_retry_gives_a_target_that_gave_up_fresh_attempts_on_the_same_pipeline(): void
    {
        $target = $this->watched();
        $target->startPipelineFix(self::JOB, '77');
        $target->recordPipelineFixResult(self::JOB, PipelineFixOutcomeEnum::NOT_FIXED, 'no idea');
        Assert::assertSame(PipelineStatusEnum::GAVE_UP, $target->pipelineStatus());

        $target->retryPipelineFix();

        Assert::assertSame(PipelineStatusEnum::WAITING, $target->pipelineStatus());
        Assert::assertSame(0, $target->pipelineFixAttempts());
        Assert::assertNull($target->pipelineSummary());
        Assert::assertTrue($target->observePipeline($this->pipeline('failed')));
    }

    /**
     * @return iterable<string, array{PipelineFixOutcomeEnum}>
     */
    public static function outcomesThatCannotBeRetried(): iterable
    {
        yield 'unrelated' => [PipelineFixOutcomeEnum::UNRELATED];
        yield 'stood down' => [PipelineFixOutcomeEnum::SUPERSEDED];
    }

    #[Test]
    #[DataProvider('outcomesThatCannotBeRetried')]
    public function only_a_fix_that_gave_up_can_be_retried(PipelineFixOutcomeEnum $outcome): void
    {
        $target = $this->watched();
        $target->startPipelineFix(self::JOB, '77');
        $target->recordPipelineFixResult(self::JOB, $outcome, 'why');

        $this->expectException(InvalidShiftTargetStateTransitionException::class);

        $target->retryPipelineFix();
    }

    #[Test]
    public function fixed_without_a_commit_to_watch_gives_up_rather_than_watching_nothing(): void
    {
        $target = $this->watched();
        $target->startPipelineFix(self::JOB, '77');

        $target->recordPipelineFixResult(self::JOB, PipelineFixOutcomeEnum::FIXED, 'fixed', null);

        Assert::assertSame(PipelineStatusEnum::GAVE_UP, $target->pipelineStatus());
    }

    #[Test]
    public function gives_up_once_the_attempts_run_out(): void
    {
        $target = $this->watched();
        $head = self::HEAD;

        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            Assert::assertTrue($target->observePipeline(new MergeRequestPipeline($head, (string) $attempt, $head, 'b', 'failed')));
            $target->startPipelineFix(self::JOB, (string) $attempt);
            $head = \str_repeat((string) $attempt, 40);
            $target->recordPipelineFixResult(self::JOB, PipelineFixOutcomeEnum::FIXED, 'tried', $head);
        }

        Assert::assertSame(3, $target->pipelineFixAttempts());
        Assert::assertFalse($target->observePipeline(new MergeRequestPipeline($head, '4', $head, 'b', 'failed')));
        Assert::assertSame(PipelineStatusEnum::GAVE_UP, $target->pipelineStatus());
    }

    #[Test]
    public function a_report_from_a_job_that_is_not_the_current_fix_does_nothing(): void
    {
        $target = $this->watched();
        $target->startPipelineFix(self::JOB, '77');

        $target->recordPipelineFixResult('0199a000-0000-7000-8000-000000000002', PipelineFixOutcomeEnum::FIXED, 'stale', self::FIXED);

        Assert::assertSame(PipelineStatusEnum::FIXING, $target->pipelineStatus());
        Assert::assertSame(self::HEAD, $target->pipelineHeadSha());
    }

    #[Test]
    public function a_late_fix_report_cannot_undo_a_merge(): void
    {
        $target = $this->watched();
        $target->startPipelineFix(self::JOB, '77');
        $target->recordMergeRequestMerged();

        $target->recordPipelineFixResult(self::JOB, PipelineFixOutcomeEnum::FIXED, 'too late', self::FIXED);

        Assert::assertSame(ShiftTargetStatusEnum::COMPLETED, $target->status());
        Assert::assertSame(PipelineStatusEnum::NOT_WATCHED, $target->pipelineStatus());
        Assert::assertNull($target->pipelineSummary());
    }

    #[Test]
    public function a_rerun_invalidates_the_fix_in_flight_and_restarts_the_attempts(): void
    {
        $target = $this->watched();
        $target->startPipelineFix(self::JOB, '77');

        $target->rerunChange('0199a000-0000-7000-8000-000000000003');
        $target->recordPipelineFixResult(self::JOB, PipelineFixOutcomeEnum::FIXED, 'late', self::FIXED);

        Assert::assertSame(PipelineStatusEnum::NOT_WATCHED, $target->pipelineStatus());
        Assert::assertSame(ShiftTargetStatusEnum::CHANGE_IN_PROGRESS, $target->status());
        Assert::assertSame(0, $target->pipelineFixAttempts());

        $target->recordChangeSuccess('again', 'refleet/change-1', 'runner', 'https://gitlab.com/a/b/-/merge_requests/9', '9', self::OTHER, true);

        Assert::assertSame(PipelineStatusEnum::WAITING, $target->pipelineStatus());
        Assert::assertSame(self::OTHER, $target->pipelineHeadSha());
    }

    #[Test]
    public function a_closed_or_cancelled_target_stops_being_watched_but_a_settled_result_stays(): void
    {
        $closed = $this->watched();
        $closed->recordMergeRequestClosed();
        Assert::assertSame(PipelineStatusEnum::NOT_WATCHED, $closed->pipelineStatus());

        $cancelled = $this->watched();
        $cancelled->cancel();
        Assert::assertSame(PipelineStatusEnum::NOT_WATCHED, $cancelled->pipelineStatus());

        $passed = $this->watched();
        $passed->observePipeline($this->pipeline('success'));
        $passed->recordMergeRequestMerged();
        Assert::assertSame(PipelineStatusEnum::PASSED, $passed->pipelineStatus());
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

    private function pipeline(string $status): MergeRequestPipeline
    {
        return new MergeRequestPipeline(self::HEAD, '77', self::HEAD, 'refleet/change-1', $status);
    }

    private function watched(): ShiftTarget
    {
        $target = $this->inProgress();
        $target->recordChangeSuccess('done', 'refleet/change-1', 'runner', 'https://gitlab.com/a/b/-/merge_requests/9', '9', self::HEAD, true);

        return $target;
    }

    private function inProgress(): ShiftTarget
    {
        $target = ShiftTarget::create(
            ShiftTargetId::generate(),
            ShiftId::generate(),
            OrganizationId::generate(),
            ProjectId::generate(),
            new ProjectSnapshot('10', 'acme/payments', 'payments', 'main'),
        );
        $target->startChange('0199a000-0000-7000-8000-000000000000');

        return $target;
    }

    private function watchStartedAt(ShiftTarget $target, \DateTimeImmutable $at): void
    {
        new \ReflectionProperty(ShiftTarget::class, 'pipelineWatchStartedAt')->setValue($target, $at);
    }
}
