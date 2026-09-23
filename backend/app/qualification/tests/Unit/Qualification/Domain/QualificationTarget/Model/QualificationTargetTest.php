<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Domain\QualificationTarget\Model;

use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationScore;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Exception\InvalidQualificationTargetStateTransitionException;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\ProjectId;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(QualificationTarget::class)]
final class QualificationTargetTest extends TestCase
{
    #[Test]
    public function creates_with_pending_status(): void
    {
        $id = QualificationTargetId::generate();
        $qualificationId = QualificationId::generate();
        $organizationId = OrganizationId::generate();
        $projectId = ProjectId::generate();

        $target = QualificationTarget::create(
            id: $id,
            qualificationId: $qualificationId,
            organizationId: $organizationId,
            projectId: $projectId,
            snapshot: new ProjectSnapshot('48210942', 'backend-team/payments-service', 'Payments Service', 'main'),
        );

        Assert::assertTrue($target->id()->equals($id));
        Assert::assertTrue($target->qualificationId()->equals($qualificationId));
        Assert::assertTrue($target->organizationId()->equals($organizationId));
        Assert::assertTrue($target->projectId()->equals($projectId));
        Assert::assertSame(QualificationTargetStatusEnum::PENDING, $target->status());
        Assert::assertFalse($target->isTerminal());
        Assert::assertNull($target->runnerName());
        Assert::assertNull($target->runnerJobId());
        Assert::assertFalse($target->overridden());

        $snapshot = $target->projectSnapshot();
        Assert::assertSame('48210942', $snapshot->externalId());
        Assert::assertSame('backend-team/payments-service', $snapshot->path());
        Assert::assertSame('Payments Service', $snapshot->name());
        Assert::assertSame('main', $snapshot->defaultBranch());
    }

    #[Test]
    public function walks_the_qualified_happy_path(): void
    {
        $target = $this->newTarget();

        $target->start('runner-job-1');
        Assert::assertSame(QualificationTargetStatusEnum::IN_PROGRESS, $target->status());
        Assert::assertSame('runner-job-1', $target->runnerJobId());
        Assert::assertNotNull($target->startedAt());

        $target->recordSuccess(QualificationScore::fromInt(5), 'Depends on acme/legacy-lib', 'runner-1');
        Assert::assertSame(QualificationTargetStatusEnum::QUALIFIED, $target->status());
        Assert::assertSame('Depends on acme/legacy-lib', $target->summary());
        Assert::assertSame('runner-1', $target->runnerName());
        Assert::assertNotNull($target->completedAt());
        Assert::assertTrue($target->isTerminal());
    }

    #[Test]
    public function records_not_qualified_outcome(): void
    {
        $target = $this->newTarget();
        $target->start('runner-job-1');

        $target->recordSuccess(QualificationScore::fromInt(2), 'Does not depend on the library', 'runner-1');

        Assert::assertSame(QualificationTargetStatusEnum::NOT_QUALIFIED, $target->status());
        Assert::assertTrue($target->isTerminal());
    }

    #[Test]
    public function records_failure(): void
    {
        $target = $this->newTarget();
        $target->start('runner-job-1');

        $target->recordFailure('Runner crashed', 'runner-1');

        Assert::assertSame(QualificationTargetStatusEnum::FAILED, $target->status());
        Assert::assertSame('Runner crashed', $target->summary());
        Assert::assertSame('runner-1', $target->runnerName());
        Assert::assertTrue($target->isTerminal());
    }

    #[Test]
    public function retries_a_failed_target_from_a_clean_slate(): void
    {
        $target = $this->newTarget();
        $target->start('runner-job-1');
        $target->recordFailure('Agent did not return a clear qualification decision', 'runner-1');

        $target->retry();

        Assert::assertSame(QualificationTargetStatusEnum::PENDING, $target->status());
        Assert::assertNull($target->summary());
        Assert::assertNull($target->runnerJobId());
        Assert::assertNull($target->runnerName());
        Assert::assertNull($target->startedAt());
        Assert::assertNull($target->completedAt());
        Assert::assertFalse($target->isTerminal());

        $target->start('runner-job-2');
        $target->recordSuccess(QualificationScore::fromInt(5), 'Depends on the library', 'runner-2');

        Assert::assertSame(QualificationTargetStatusEnum::QUALIFIED, $target->status());
        Assert::assertSame('runner-job-2', $target->runnerJobId());
    }

    #[Test]
    public function cannot_retry_a_target_that_did_not_fail(): void
    {
        $target = $this->qualifiedTarget();

        $this->expectException(InvalidQualificationTargetStateTransitionException::class);

        $target->retry();
    }

    #[Test]
    public function cannot_start_twice(): void
    {
        $target = $this->newTarget();
        $target->start('runner-job-1');

        $this->expectException(InvalidQualificationTargetStateTransitionException::class);

        $target->start('runner-job-2');
    }

    #[Test]
    public function cannot_record_success_before_starting(): void
    {
        $target = $this->newTarget();

        $this->expectException(InvalidQualificationTargetStateTransitionException::class);

        $target->recordSuccess(QualificationScore::fromInt(5), 'Qualified', 'runner-1');
    }

    #[Test]
    public function overrides_from_pending(): void
    {
        $target = $this->newTarget();

        $target->override(true, 'Manually confirmed');

        Assert::assertSame(QualificationTargetStatusEnum::QUALIFIED, $target->status());
        Assert::assertTrue($target->overridden());
        Assert::assertSame('Manually confirmed', $target->overrideNote());
        Assert::assertNotNull($target->completedAt());
    }

    #[Test]
    public function overrides_a_qualified_target_to_not_qualified_without_erasing_the_original_summary(): void
    {
        $target = $this->qualifiedTarget();

        $target->override(false, 'Actually out of scope');

        Assert::assertSame(QualificationTargetStatusEnum::NOT_QUALIFIED, $target->status());
        Assert::assertTrue($target->overridden());
        Assert::assertSame('Actually out of scope', $target->overrideNote());
        Assert::assertSame('Qualified', $target->summary());
    }

    #[Test]
    public function overrides_after_an_automatic_failure(): void
    {
        $target = $this->newTarget();
        $target->start('runner-job-1');
        $target->recordFailure('Runner crashed', 'runner-1');

        $target->override(true, null);

        Assert::assertSame(QualificationTargetStatusEnum::QUALIFIED, $target->status());
        Assert::assertTrue($target->overridden());
    }

    #[Test]
    public function cannot_override_while_a_qualification_job_is_in_progress(): void
    {
        $target = $this->newTarget();
        $target->start('runner-job-1');

        $this->expectException(InvalidQualificationTargetStateTransitionException::class);

        $target->override(true, null);
    }

    #[Test]
    public function cancels_a_non_terminal_target(): void
    {
        $target = $this->newTarget();

        $target->cancel();

        Assert::assertSame(QualificationTargetStatusEnum::CANCELLED, $target->status());
        Assert::assertTrue($target->isTerminal());
    }

    #[Test]
    public function cancels_a_target_that_is_in_progress(): void
    {
        $target = $this->newTarget();
        $target->start('runner-job-1');

        $target->cancel();

        Assert::assertSame(QualificationTargetStatusEnum::CANCELLED, $target->status());
    }

    #[Test]
    public function cannot_cancel_a_terminal_target(): void
    {
        $target = $this->newTarget();
        $target->start('runner-job-1');
        $target->recordFailure('boom', 'runner-1');

        $this->expectException(InvalidQualificationTargetStateTransitionException::class);

        $target->cancel();
    }

    private function newTarget(): QualificationTarget
    {
        return QualificationTarget::create(
            id: QualificationTargetId::generate(),
            qualificationId: QualificationId::generate(),
            organizationId: OrganizationId::generate(),
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );
    }

    private function qualifiedTarget(): QualificationTarget
    {
        $target = $this->newTarget();
        $target->start('runner-job-1');
        $target->recordSuccess(QualificationScore::fromInt(5), 'Qualified', 'runner-1');

        return $target;
    }
}
