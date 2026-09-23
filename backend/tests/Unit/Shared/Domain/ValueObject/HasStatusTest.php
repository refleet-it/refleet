<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\HasStatus;
use App\Shared\Domain\ValueObject\Status;
use App\Tests\Helpers\Shared\Domain\ValueObject\HasStatusTestSubject;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversTrait(HasStatus::class)]
final class HasStatusTest extends TestCase
{
    #[Test]
    public function status_returns_current_status_after_set_status(): void
    {
        // Arrange
        $subject = new HasStatusTestSubject();

        // Act
        $subject->setStatusPublic(Status::ACTIVE);

        $status = $subject->status();

        // Assert
        Assert::assertSame(Status::ACTIVE, $status);
    }

    #[Test]
    public function is_active_reflects_current_status(): void
    {
        // Arrange
        $subject = new HasStatusTestSubject(Status::ACTIVE);

        // Act
        $activeResult = $subject->isActive();
        $subject->setStatusPublic(Status::DELETED);
        $deletedResult = $subject->isActive();

        // Assert
        Assert::assertTrue($activeResult);
        Assert::assertFalse($deletedResult);
    }

    #[Test]
    public function is_deleted_reflects_current_status(): void
    {
        // Arrange
        $subject = new HasStatusTestSubject(Status::DELETED);

        // Act
        $deletedResult = $subject->isDeleted();
        $subject->setStatusPublic(Status::ACTIVE);
        $activeResult = $subject->isDeleted();

        // Assert
        Assert::assertTrue($deletedResult);
        Assert::assertFalse($activeResult);
    }

    #[Test]
    public function activate_sets_status_to_active_and_updates_predicates(): void
    {
        // Arrange
        $subject = new HasStatusTestSubject(Status::DELETED);

        // Act
        $subject->activatePublic();

        // Assert
        Assert::assertSame(Status::ACTIVE, $subject->status());
        Assert::assertTrue($subject->isActive());
        Assert::assertFalse($subject->isDeleted());
    }

    #[Test]
    public function delete_sets_status_to_deleted_and_updates_predicates(): void
    {
        // Arrange
        $subject = new HasStatusTestSubject(Status::ACTIVE);

        // Act
        $subject->deletePublic();

        // Assert
        Assert::assertSame(Status::DELETED, $subject->status());
        Assert::assertFalse($subject->isActive());
        Assert::assertTrue($subject->isDeleted());
    }

    #[Test]
    public function accessing_status_before_initialization_throws_error(): void
    {
        // Arrange
        $subject = new HasStatusTestSubject();

        // Act
        $thrown = null;

        try {
            $subject->status();
        } catch (\Error $error) {
            $thrown = $error;
        }

        // Assert
        Assert::assertInstanceOf(\Error::class, $thrown);
    }
}
