<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject\Sorting;

use App\Shared\Domain\Exception\InvalidSortFieldException;
use App\Shared\Domain\ValueObject\Sorting\SortDirection;
use App\Shared\Domain\ValueObject\Sorting\SortField;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SortParameters::class)]
#[UsesClass(SortField::class)]
#[UsesClass(SortDirection::class)]
final class SortParametersTest extends TestCase
{
    #[Test]
    public function from_request_returns_null_when_sort_by_is_not_provided(): void
    {
        // Arrange
        $sortBy = null;

        // Act
        $parameters = SortParameters::fromRequest($sortBy, 'desc', ['name']);

        // Assert
        Assert::assertNull($parameters);
    }

    #[Test]
    public function from_request_defaults_to_ascending_when_direction_is_not_provided(): void
    {
        // Arrange
        $sortBy = 'name';

        // Act
        $parameters = SortParameters::fromRequest($sortBy);

        // Assert
        Assert::assertNotNull($parameters);
        Assert::assertSame('name', $parameters->getField());
        Assert::assertSame(SortDirection::ASC, $parameters->getDirection());
        Assert::assertTrue($parameters->isAscending());
        Assert::assertFalse($parameters->isDescending());
    }

    #[Test]
    public function from_request_uses_direction_parser_case_insensitively(): void
    {
        // Arrange
        $sortBy = 'createdAt';
        $sortDirection = 'DESCENDING';

        // Act
        $parameters = SortParameters::fromRequest($sortBy, $sortDirection);

        // Assert
        Assert::assertNotNull($parameters);
        Assert::assertSame('createdAt', $parameters->getField());
        Assert::assertSame(SortDirection::DESC, $parameters->getDirection());
        Assert::assertTrue($parameters->isDescending());
        Assert::assertFalse($parameters->isAscending());
    }

    #[Test]
    public function from_request_rejects_empty_sort_field(): void
    {
        // Arrange
        $sortBy = '';

        // Act
        try {
            SortParameters::fromRequest($sortBy);
            Assert::fail('Expected InvalidSortFieldException to be thrown.');
        } catch (InvalidSortFieldException $invalidSortFieldException) {
            // Assert
            Assert::assertSame('Sort field cannot be empty', $invalidSortFieldException->getMessage());
        }
    }

    #[Test]
    public function from_request_rejects_disallowed_field_when_whitelist_is_provided(): void
    {
        // Arrange
        $sortBy = 'email';
        $allowedFields = ['name', 'createdAt'];

        // Act
        try {
            SortParameters::fromRequest($sortBy, null, $allowedFields);
            Assert::fail('Expected InvalidSortFieldException to be thrown.');
        } catch (InvalidSortFieldException $invalidSortFieldException) {
            // Assert
            Assert::assertSame(
                'Sort field "email" is not allowed. Allowed fields: name, createdAt',
                $invalidSortFieldException->getMessage(),
            );
        }
    }

    #[Test]
    public function from_request_propagates_invalid_direction_exception(): void
    {
        // Arrange
        $sortBy = 'name';
        $invalidDirection = 'up';

        // Act
        try {
            SortParameters::fromRequest($sortBy, $invalidDirection);
            Assert::fail('Expected InvalidArgumentException to be thrown.');
        } catch (\InvalidArgumentException $invalidArgumentException) {
            // Assert
            Assert::assertSame('Invalid sort direction: up', $invalidArgumentException->getMessage());
        }
    }
}
