<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject\Sorting;

use App\Shared\Domain\ValueObject\Sorting\SortDirection;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SortDirection::class)]
final class SortDirectionTest extends TestCase
{
    #[Test]
    public function from_string_accepts_common_values_case_insensitively(): void
    {
        // Act
        $asc1 = SortDirection::fromString('asc');
        $asc2 = SortDirection::fromString('ASC');
        $asc3 = SortDirection::fromString('ascending');
        $desc1 = SortDirection::fromString('desc');
        $desc2 = SortDirection::fromString('DESC');
        $desc3 = SortDirection::fromString('descending');

        // Assert
        Assert::assertSame(SortDirection::ASC, $asc1);
        Assert::assertSame(SortDirection::ASC, $asc2);
        Assert::assertSame(SortDirection::ASC, $asc3);
        Assert::assertSame(SortDirection::DESC, $desc1);
        Assert::assertSame(SortDirection::DESC, $desc2);
        Assert::assertSame(SortDirection::DESC, $desc3);
    }

    #[Test]
    public function from_string_invalid_value_throws(): void
    {
        // Arrange
        $invalid = 'upwards';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sort direction: '.$invalid);

        // Act
        SortDirection::fromString($invalid);
    }

    #[Test]
    public function predicates_match_direction(): void
    {
        // Assert
        Assert::assertTrue(SortDirection::ASC->isAscending());
        Assert::assertFalse(SortDirection::ASC->isDescending());

        Assert::assertTrue(SortDirection::DESC->isDescending());
        Assert::assertFalse(SortDirection::DESC->isAscending());
    }

    #[Test]
    public function backed_values_are_correct(): void
    {
        // Assert
        Assert::assertSame('asc', SortDirection::ASC->value);
        Assert::assertSame('desc', SortDirection::DESC->value);
    }
}
