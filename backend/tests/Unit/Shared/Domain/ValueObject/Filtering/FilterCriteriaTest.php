<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject\Filtering;

use App\Shared\Domain\Exception\InvalidFilterCriteriaException;
use App\Shared\Domain\ValueObject\Filtering\FilterCriteria;
use App\Shared\Domain\ValueObject\Filtering\FilterOperator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilterCriteria::class)]
#[UsesClass(FilterOperator::class)]
final class FilterCriteriaTest extends TestCase
{
    #[Test]
    public function is_allowed_when_allowed_fields_are_unrestricted(): void
    {
        // Act
        $criteria = new FilterCriteria('status', FilterOperator::EQUALS, 'active');

        // Assert
        Assert::assertTrue($criteria->isAllowed());
        Assert::assertSame('status', $criteria->getField());
        Assert::assertSame(FilterOperator::EQUALS, $criteria->getOperator());
        Assert::assertSame('active', $criteria->getValue());
    }

    #[Test]
    public function is_allowed_when_field_is_in_allowed_list(): void
    {
        // Act
        $criteria = new FilterCriteria('status', FilterOperator::EQUALS, 'active', ['status', 'name']);

        // Assert
        Assert::assertTrue($criteria->isAllowed());
    }

    #[Test]
    public function constructor_with_empty_field_throws(): void
    {
        // Assert
        $this->expectException(InvalidFilterCriteriaException::class);
        $this->expectExceptionMessage('Filter field cannot be empty');

        // Act
        new FilterCriteria('', FilterOperator::EQUALS, 'active');
    }

    #[Test]
    public function constructor_with_disallowed_field_throws(): void
    {
        // Arrange
        $allowedFields = ['name', 'email'];

        // Assert
        $this->expectException(InvalidFilterCriteriaException::class);
        $this->expectExceptionMessage('Filter field "status" is not allowed. Allowed fields: name, email');

        // Act
        new FilterCriteria('status', FilterOperator::EQUALS, 'active', $allowedFields);
    }

    #[Test]
    public function constructor_requires_value_when_operator_demands_it(): void
    {
        // Assert
        $this->expectException(InvalidFilterCriteriaException::class);
        $this->expectExceptionMessage('Filter operator "eq" requires a value');

        // Act
        new FilterCriteria('status', FilterOperator::EQUALS, null, ['status']);
    }

    #[Test]
    public function constructor_requires_array_value_for_array_supporting_operator(): void
    {
        // Assert
        $this->expectException(InvalidFilterCriteriaException::class);
        $this->expectExceptionMessage('Filter operator "in" requires an array value');

        // Act
        new FilterCriteria('status', FilterOperator::IN, 'active', ['status']);
    }

    #[Test]
    public function from_request_parses_operator_and_keeps_configuration(): void
    {
        // Act
        $criteria = FilterCriteria::fromRequest('status', 'EQ', 'active', ['status']);

        // Assert
        Assert::assertSame('status', $criteria->getField());
        Assert::assertSame(FilterOperator::EQUALS, $criteria->getOperator());
        Assert::assertSame('active', $criteria->getValue());
        Assert::assertTrue($criteria->isAllowed());
    }
}
