<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Status;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase
{
    #[Test]
    public function active_value_is_active_string(): void
    {
        // Assert
        Assert::assertSame('active', Status::ACTIVE->value);
    }

    #[Test]
    public function deleted_value_is_deleted_string(): void
    {
        // Assert
        Assert::assertSame('deleted', Status::DELETED->value);
    }

    #[Test]
    public function from_returns_correct_case_for_valid_values(): void
    {
        // Act
        $active = Status::from('active');
        $deleted = Status::from('deleted');

        // Assert
        Assert::assertSame(Status::ACTIVE, $active);
        Assert::assertSame(Status::DELETED, $deleted);
    }

    #[Test]
    public function try_from_returns_null_for_invalid_value(): void
    {
        // Act
        $result = Status::tryFrom('unknown');

        // Assert
        Assert::assertNull($result);
    }

    #[Test]
    public function enum_cases_are_distinct_and_comparable(): void
    {
        // Assert
        Assert::assertNotSame(Status::ACTIVE, Status::DELETED);
        Assert::assertSame(Status::ACTIVE, Status::ACTIVE);
        Assert::assertNotSame(Status::DELETED, Status::ACTIVE);
    }
}
