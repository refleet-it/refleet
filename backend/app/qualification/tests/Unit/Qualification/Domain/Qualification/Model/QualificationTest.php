<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Domain\Qualification\Model;

use App\Qualification\Qualification\Domain\Qualification\Enum\QualificationStatusEnum;
use App\Qualification\Qualification\Domain\Qualification\Event\QualificationCancelled;
use App\Qualification\Qualification\Domain\Qualification\Event\QualificationCompleted;
use App\Qualification\Qualification\Domain\Qualification\Exception\InvalidQualificationStateTransitionException;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationArchivedException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Qualification::class)]
final class QualificationTest extends TestCase
{
    #[Test]
    public function drafts_with_static_criteria(): void
    {
        $id = QualificationId::generate();
        $organizationId = OrganizationId::generate();
        $createdBy = AccountId::generate();

        $qualification = Qualification::draft(
            id: $id,
            organizationId: $organizationId,
            title: 'Bump acme/legacy-lib',
            description: 'Removes the deprecated dependency',
            createdBy: $createdBy,
            criteria: QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
        );

        Assert::assertTrue($qualification->id()->equals($id));
        Assert::assertTrue($qualification->organizationId()->equals($organizationId));
        Assert::assertSame('Bump acme/legacy-lib', $qualification->title());
        Assert::assertSame('Removes the deprecated dependency', $qualification->description());
        Assert::assertTrue($qualification->createdBy()->equals($createdBy));
        Assert::assertSame(QualificationStatusEnum::DRAFT, $qualification->status());
        Assert::assertNull($qualification->cancelReason());

        $criteria = $qualification->criteria();
        Assert::assertSame('Does this repository depend on acme/legacy-lib?', $criteria->prompt());
        Assert::assertNull($criteria->model());
    }

    #[Test]
    public function drafts_with_ai_criteria(): void
    {
        $qualification = $this->draftedQualification(
            QualificationCriteria::ai('Does this repo use acme/legacy-lib?', 'claude-sonnet-5'),
        );

        $criteria = $qualification->criteria();
        Assert::assertSame('Does this repo use acme/legacy-lib?', $criteria->prompt());
        Assert::assertSame('claude-sonnet-5', $criteria->model());
    }

    #[Test]
    public function records_no_domain_event_on_draft(): void
    {
        $qualification = $this->draftedQualification();

        Assert::assertCount(0, $qualification->getRecordedDomainEvents());
    }

    #[Test]
    public function records_qualification_completed_event(): void
    {
        $qualification = $this->draftedQualification();
        $qualification->start();
        $qualification->getRecordedDomainEvents();

        $qualification->complete();

        $events = $qualification->getRecordedDomainEvents();

        Assert::assertCount(1, $events);
        Assert::assertInstanceOf(QualificationCompleted::class, $events[0]);
        Assert::assertTrue($events[0]->qualificationId->equals($qualification->id()));
        Assert::assertTrue($events[0]->organizationId->equals($qualification->organizationId()));
        Assert::assertTrue($events[0]->createdBy->equals($qualification->createdBy()));
        Assert::assertSame('Test qualification', $events[0]->title);

        // getRecordedDomainEvents() drains the buffer.
        Assert::assertCount(0, $qualification->getRecordedDomainEvents());
    }

    #[Test]
    public function records_qualification_cancelled_event(): void
    {
        $qualification = $this->draftedQualification();
        $qualification->getRecordedDomainEvents();

        $qualification->cancel('No longer needed');

        $events = $qualification->getRecordedDomainEvents();

        Assert::assertCount(1, $events);
        Assert::assertInstanceOf(QualificationCancelled::class, $events[0]);
        Assert::assertTrue($events[0]->qualificationId->equals($qualification->id()));
        Assert::assertTrue($events[0]->organizationId->equals($qualification->organizationId()));
        Assert::assertTrue($events[0]->createdBy->equals($qualification->createdBy()));
        Assert::assertSame('Test qualification', $events[0]->title);
        Assert::assertSame('No longer needed', $events[0]->reason);
    }

    #[Test]
    public function walks_the_full_happy_path(): void
    {
        $qualification = $this->draftedQualification();

        $qualification->start();
        Assert::assertSame(QualificationStatusEnum::RUNNING, $qualification->status());
        Assert::assertNotNull($qualification->startedAt());

        $qualification->complete();
        Assert::assertSame(QualificationStatusEnum::COMPLETED, $qualification->status());
        Assert::assertNotNull($qualification->completedAt());
    }

    #[Test]
    public function resumes_a_completed_qualification_and_completes_it_again(): void
    {
        $qualification = $this->draftedQualification();
        $qualification->start();
        $qualification->complete();
        $qualification->getRecordedDomainEvents();

        $qualification->resume();

        Assert::assertSame(QualificationStatusEnum::RUNNING, $qualification->status());
        Assert::assertNull($qualification->completedAt());
        Assert::assertNotNull($qualification->startedAt());

        $qualification->complete();

        Assert::assertSame(QualificationStatusEnum::COMPLETED, $qualification->status());
        Assert::assertCount(1, $qualification->getRecordedDomainEvents());
    }

    #[Test]
    public function cannot_resume_a_qualification_that_is_not_completed(): void
    {
        $qualification = $this->draftedQualification();
        $qualification->start();

        $this->expectException(InvalidQualificationStateTransitionException::class);

        $qualification->resume();
    }

    #[Test]
    public function cannot_start_twice(): void
    {
        $qualification = $this->draftedQualification();
        $qualification->start();

        $this->expectException(InvalidQualificationStateTransitionException::class);

        $qualification->start();
    }

    #[Test]
    public function cannot_complete_before_starting(): void
    {
        $qualification = $this->draftedQualification();

        $this->expectException(InvalidQualificationStateTransitionException::class);

        $qualification->complete();
    }

    #[Test]
    public function cancels_from_a_non_terminal_state_with_reason(): void
    {
        $qualification = $this->draftedQualification();
        $qualification->start();

        $qualification->cancel('No longer needed');

        Assert::assertSame(QualificationStatusEnum::CANCELLED, $qualification->status());
        Assert::assertSame('No longer needed', $qualification->cancelReason());
        Assert::assertNotNull($qualification->cancelledAt());
    }

    #[Test]
    public function cancels_from_draft_without_a_reason(): void
    {
        $qualification = $this->draftedQualification();

        $qualification->cancel(null);

        Assert::assertSame(QualificationStatusEnum::CANCELLED, $qualification->status());
        Assert::assertNull($qualification->cancelReason());
    }

    #[Test]
    public function cannot_cancel_a_completed_qualification(): void
    {
        $qualification = $this->draftedQualification();
        $qualification->start();
        $qualification->complete();

        $this->expectException(InvalidQualificationStateTransitionException::class);

        $qualification->cancel('too late');
    }

    #[Test]
    public function cannot_cancel_an_already_cancelled_qualification(): void
    {
        $qualification = $this->draftedQualification();
        $qualification->cancel('first cancel');

        $this->expectException(InvalidQualificationStateTransitionException::class);

        $qualification->cancel('second cancel');
    }

    #[Test]
    public function archives_a_completed_qualification(): void
    {
        $qualification = $this->draftedQualification();
        $qualification->start();
        $qualification->complete();

        $archivedAt = new \DateTimeImmutable('2026-09-16 10:00:00');

        $qualification->archive($archivedAt);

        Assert::assertTrue($qualification->isArchived());
        Assert::assertSame($archivedAt, $qualification->archivedAt());
        Assert::assertSame(QualificationStatusEnum::COMPLETED, $qualification->status());
    }

    #[Test]
    public function archives_a_draft_and_a_cancelled_qualification(): void
    {
        $draft = $this->draftedQualification();
        $draft->archive(new \DateTimeImmutable());
        Assert::assertTrue($draft->isArchived());

        $cancelled = $this->draftedQualification();
        $cancelled->cancel(null);
        $cancelled->archive(new \DateTimeImmutable());
        Assert::assertTrue($cancelled->isArchived());
    }

    #[Test]
    public function cannot_archive_a_running_qualification(): void
    {
        $qualification = $this->draftedQualification();
        $qualification->start();

        $this->expectException(InvalidQualificationStateTransitionException::class);

        $qualification->archive(new \DateTimeImmutable());
    }

    #[Test]
    public function cannot_archive_twice(): void
    {
        $qualification = $this->draftedQualification();
        $qualification->archive(new \DateTimeImmutable());

        $this->expectException(QualificationArchivedException::class);

        $qualification->archive(new \DateTimeImmutable());
    }

    #[Test]
    public function an_archived_qualification_is_frozen(): void
    {
        $qualification = $this->draftedQualification();
        $qualification->archive(new \DateTimeImmutable());

        $this->expectException(QualificationArchivedException::class);

        $qualification->start();
    }

    private function draftedQualification(?QualificationCriteria $criteria = null): Qualification
    {
        return Qualification::draft(
            id: QualificationId::generate(),
            organizationId: OrganizationId::generate(),
            title: 'Test qualification',
            description: null,
            createdBy: AccountId::generate(),
            criteria: $criteria ?? QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
        );
    }
}
