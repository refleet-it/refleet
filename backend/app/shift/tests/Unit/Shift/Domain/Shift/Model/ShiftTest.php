<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Domain\Shift\Model;

use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shift\Shift\Domain\Shift\Enum\ShiftStatusEnum;
use App\Shift\Shift\Domain\Shift\Event\ShiftCancelled;
use App\Shift\Shift\Domain\Shift\Event\ShiftCompleted;
use App\Shift\Shift\Domain\Shift\Exception\ChangeCriteriaNotDefinedException;
use App\Shift\Shift\Domain\Shift\Exception\InvalidShiftStateTransitionException;
use App\Shift\Shift\Domain\Shift\Exception\ShiftArchivedException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\ValueObject\AccountId;
use App\Shift\Shift\Domain\Shift\ValueObject\ChangeCriteria;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\QualificationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Shift::class)]
final class ShiftTest extends TestCase
{
    #[Test]
    public function drafts_without_a_qualification(): void
    {
        $id = ShiftId::generate();
        $organizationId = OrganizationId::generate();
        $createdBy = AccountId::generate();

        $shift = Shift::draft(
            id: $id,
            organizationId: $organizationId,
            title: 'Bump acme/legacy-lib to v3',
            description: 'Removes the deprecated dependency',
            createdBy: $createdBy,
            qualificationId: null,
        );

        Assert::assertTrue($shift->id()->equals($id));
        Assert::assertTrue($shift->organizationId()->equals($organizationId));
        Assert::assertSame('Bump acme/legacy-lib to v3', $shift->title());
        Assert::assertSame('Removes the deprecated dependency', $shift->description());
        Assert::assertTrue($shift->createdBy()->equals($createdBy));
        Assert::assertSame(ShiftStatusEnum::DRAFT, $shift->status());
        Assert::assertNull($shift->qualificationId());
        Assert::assertNull($shift->changeCriteria());
        Assert::assertNull($shift->cancelReason());
    }

    #[Test]
    public function drafts_from_a_qualification(): void
    {
        $qualificationId = QualificationId::generate();

        $shift = Shift::draft(
            id: ShiftId::generate(),
            organizationId: OrganizationId::generate(),
            title: 'From qualification',
            description: null,
            createdBy: AccountId::generate(),
            qualificationId: $qualificationId,
        );

        Assert::assertTrue($shift->qualificationId()?->equals($qualificationId));
    }

    #[Test]
    public function defines_change_criteria(): void
    {
        $shift = $this->draftedShift();

        $shift->defineChange(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));

        $criteria = $shift->changeCriteria();
        Assert::assertNotNull($criteria);
        Assert::assertSame('Bump acme/legacy-lib to ^3.0', $criteria->prompt());
        Assert::assertNull($criteria->engine());
    }

    #[Test]
    public function redefining_change_criteria_before_start_replaces_the_previous_one(): void
    {
        $shift = $this->draftedShift();

        $shift->defineChange(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));
        $shift->defineChange(ChangeCriteria::ai('Apply the migration', 'claude-haiku-4-5-20251001', CriteriaEngineEnum::KIRO));

        $criteria = $shift->changeCriteria();
        Assert::assertNotNull($criteria);
        Assert::assertSame(CriteriaEngineEnum::KIRO, $criteria->engine());

        $shift->defineChange(ChangeCriteria::ai('Apply the migration', 'claude-haiku-4-5-20251001'));
        $criteria = $shift->changeCriteria();
        Assert::assertNotNull($criteria);
        Assert::assertSame('Apply the migration', $criteria->prompt());
        Assert::assertSame('claude-haiku-4-5-20251001', $criteria->model());
    }

    #[Test]
    public function cannot_define_change_once_change_has_started(): void
    {
        $shift = $this->draftedShift();
        $shift->defineChange(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));
        $shift->startChange();

        $this->expectException(InvalidShiftStateTransitionException::class);

        $shift->defineChange(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));
    }

    #[Test]
    public function starts_the_change_once_criteria_are_defined(): void
    {
        $shift = $this->draftedShift();
        $shift->defineChange(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));

        $shift->startChange();

        Assert::assertSame(ShiftStatusEnum::APPLYING_CHANGE, $shift->status());
        Assert::assertNotNull($shift->changeStartedAt());
    }

    #[Test]
    public function cannot_start_change_without_defined_criteria(): void
    {
        $shift = $this->draftedShift();

        $this->expectException(ChangeCriteriaNotDefinedException::class);

        $shift->startChange();
    }

    #[Test]
    public function a_trial_run_of_one_target_leaves_a_draft_a_draft(): void
    {
        $shift = $this->draftedShift();
        $shift->defineChange(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));

        $shift->startTargetChange();

        Assert::assertSame(ShiftStatusEnum::DRAFT, $shift->status());
        Assert::assertNull($shift->changeStartedAt());
        $shift->defineChange(ChangeCriteria::ai('Bump acme/legacy-lib to ^4.0'));
    }

    #[Test]
    public function cannot_trial_run_a_target_without_defined_criteria(): void
    {
        $shift = $this->draftedShift();

        $this->expectException(ChangeCriteriaNotDefinedException::class);

        $shift->startTargetChange();
    }

    #[Test]
    public function running_a_target_again_reopens_a_completed_shift(): void
    {
        $shift = $this->startedShift();
        $shift->complete();

        $shift->startTargetChange();

        Assert::assertSame(ShiftStatusEnum::APPLYING_CHANGE, $shift->status());
        Assert::assertNull($shift->completedAt());
    }

    #[Test]
    public function running_a_target_while_applying_changes_nothing_on_the_shift(): void
    {
        $shift = $this->startedShift();

        $shift->startTargetChange();

        Assert::assertSame(ShiftStatusEnum::APPLYING_CHANGE, $shift->status());
    }

    #[Test]
    public function cannot_run_a_target_of_a_cancelled_shift(): void
    {
        $shift = $this->startedShift();
        $shift->cancel(null);

        $this->expectException(InvalidShiftStateTransitionException::class);

        $shift->startTargetChange();
    }

    #[Test]
    public function cannot_start_change_twice(): void
    {
        $shift = $this->draftedShift();
        $shift->defineChange(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));
        $shift->startChange();

        $this->expectException(InvalidShiftStateTransitionException::class);

        $shift->startChange();
    }

    #[Test]
    public function completes_from_applying_change(): void
    {
        $shift = $this->startedShift();

        $shift->complete();

        Assert::assertSame(ShiftStatusEnum::COMPLETED, $shift->status());
        Assert::assertNotNull($shift->completedAt());
    }

    #[Test]
    public function cannot_complete_before_applying_change(): void
    {
        $shift = $this->draftedShift();

        $this->expectException(InvalidShiftStateTransitionException::class);

        $shift->complete();
    }

    #[Test]
    public function records_shift_completed_event(): void
    {
        $shift = $this->startedShift();
        $shift->getRecordedDomainEvents();

        $shift->complete();

        $events = $shift->getRecordedDomainEvents();

        Assert::assertCount(1, $events);
        Assert::assertInstanceOf(ShiftCompleted::class, $events[0]);
        Assert::assertTrue($events[0]->shiftId->equals($shift->id()));
        Assert::assertTrue($events[0]->organizationId->equals($shift->organizationId()));
        Assert::assertTrue($events[0]->createdBy->equals($shift->createdBy()));
        Assert::assertSame('Test shift', $events[0]->title);

        Assert::assertCount(0, $shift->getRecordedDomainEvents());
    }

    #[Test]
    public function cancels_from_a_non_terminal_state_with_a_reason(): void
    {
        $shift = $this->draftedShift();

        $shift->cancel('No longer needed');

        Assert::assertSame(ShiftStatusEnum::CANCELLED, $shift->status());
        Assert::assertSame('No longer needed', $shift->cancelReason());
        Assert::assertNotNull($shift->cancelledAt());
    }

    #[Test]
    public function cancels_from_draft_without_a_reason(): void
    {
        $shift = $this->draftedShift();

        $shift->cancel(null);

        Assert::assertSame(ShiftStatusEnum::CANCELLED, $shift->status());
        Assert::assertNull($shift->cancelReason());
    }

    #[Test]
    public function cancels_from_applying_change(): void
    {
        $shift = $this->startedShift();

        $shift->cancel('Superseded');

        Assert::assertSame(ShiftStatusEnum::CANCELLED, $shift->status());
    }

    #[Test]
    public function records_shift_cancelled_event(): void
    {
        $shift = $this->draftedShift();
        $shift->getRecordedDomainEvents();

        $shift->cancel('No longer needed');

        $events = $shift->getRecordedDomainEvents();

        Assert::assertCount(1, $events);
        Assert::assertInstanceOf(ShiftCancelled::class, $events[0]);
        Assert::assertTrue($events[0]->shiftId->equals($shift->id()));
        Assert::assertSame('No longer needed', $events[0]->reason);
    }

    #[Test]
    public function cannot_cancel_a_completed_shift(): void
    {
        $shift = $this->startedShift();
        $shift->complete();

        $this->expectException(InvalidShiftStateTransitionException::class);

        $shift->cancel('too late');
    }

    #[Test]
    public function cannot_cancel_an_already_cancelled_shift(): void
    {
        $shift = $this->draftedShift();
        $shift->cancel('first cancel');

        $this->expectException(InvalidShiftStateTransitionException::class);

        $shift->cancel('second cancel');
    }

    #[Test]
    public function archives_a_completed_shift(): void
    {
        $shift = $this->startedShift();
        $shift->complete();

        $archivedAt = new \DateTimeImmutable('2026-09-16 10:00:00');

        $shift->archive($archivedAt);

        Assert::assertTrue($shift->isArchived());
        Assert::assertSame($archivedAt, $shift->archivedAt());
        Assert::assertSame(ShiftStatusEnum::COMPLETED, $shift->status());
    }

    #[Test]
    public function archives_a_draft_and_a_cancelled_shift(): void
    {
        $draft = $this->draftedShift();
        $draft->archive(new \DateTimeImmutable());
        Assert::assertTrue($draft->isArchived());

        $cancelled = $this->draftedShift();
        $cancelled->cancel(null);
        $cancelled->archive(new \DateTimeImmutable());
        Assert::assertTrue($cancelled->isArchived());
    }

    #[Test]
    public function cannot_archive_a_shift_applying_its_change(): void
    {
        $shift = $this->startedShift();

        $this->expectException(InvalidShiftStateTransitionException::class);

        $shift->archive(new \DateTimeImmutable());
    }

    #[Test]
    public function cannot_archive_twice(): void
    {
        $shift = $this->draftedShift();
        $shift->archive(new \DateTimeImmutable());

        $this->expectException(ShiftArchivedException::class);

        $shift->archive(new \DateTimeImmutable());
    }

    #[Test]
    public function an_archived_shift_is_frozen(): void
    {
        $shift = $this->draftedShift();
        $shift->archive(new \DateTimeImmutable());

        $this->expectException(ShiftArchivedException::class);

        $shift->defineChange(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));
    }

    private function draftedShift(): Shift
    {
        return Shift::draft(
            id: ShiftId::generate(),
            organizationId: OrganizationId::generate(),
            title: 'Test shift',
            description: null,
            createdBy: AccountId::generate(),
            qualificationId: null,
        );
    }

    private function startedShift(): Shift
    {
        $shift = $this->draftedShift();
        $shift->defineChange(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));
        $shift->startChange();

        return $shift;
    }
}
