<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject\Filtering;

use App\Shared\Domain\ValueObject\Filtering\FilterOperator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilterOperator::class)]
final class FilterOperatorTest extends TestCase
{
    #[Test]
    public function from_string_accepts_common_values_case_insensitively(): void
    {
        // Equals
        Assert::assertSame(FilterOperator::EQUALS, FilterOperator::fromString('eq'));
        Assert::assertSame(FilterOperator::EQUALS, FilterOperator::fromString('EQUALS'));
        Assert::assertSame(FilterOperator::EQUALS, FilterOperator::fromString('='));

        // Not equals
        Assert::assertSame(FilterOperator::NOT_EQUALS, FilterOperator::fromString('ne'));
        Assert::assertSame(FilterOperator::NOT_EQUALS, FilterOperator::fromString('NOT_EQUALS'));
        Assert::assertSame(FilterOperator::NOT_EQUALS, FilterOperator::fromString('!='));

        // Greater than
        Assert::assertSame(FilterOperator::GREATER_THAN, FilterOperator::fromString('gt'));
        Assert::assertSame(FilterOperator::GREATER_THAN, FilterOperator::fromString('GREATER_THAN'));
        Assert::assertSame(FilterOperator::GREATER_THAN, FilterOperator::fromString('>'));

        // Greater than or equal
        Assert::assertSame(FilterOperator::GREATER_THAN_OR_EQUAL, FilterOperator::fromString('gte'));
        Assert::assertSame(FilterOperator::GREATER_THAN_OR_EQUAL, FilterOperator::fromString('GREATER_THAN_OR_EQUAL'));
        Assert::assertSame(FilterOperator::GREATER_THAN_OR_EQUAL, FilterOperator::fromString('>='));

        // Less than
        Assert::assertSame(FilterOperator::LESS_THAN, FilterOperator::fromString('lt'));
        Assert::assertSame(FilterOperator::LESS_THAN, FilterOperator::fromString('LESS_THAN'));
        Assert::assertSame(FilterOperator::LESS_THAN, FilterOperator::fromString('<'));

        // Less than or equal
        Assert::assertSame(FilterOperator::LESS_THAN_OR_EQUAL, FilterOperator::fromString('lte'));
        Assert::assertSame(FilterOperator::LESS_THAN_OR_EQUAL, FilterOperator::fromString('LESS_THAN_OR_EQUAL'));
        Assert::assertSame(FilterOperator::LESS_THAN_OR_EQUAL, FilterOperator::fromString('<='));

        // Contains / like
        Assert::assertSame(FilterOperator::CONTAINS, FilterOperator::fromString('contains'));
        Assert::assertSame(FilterOperator::CONTAINS, FilterOperator::fromString('LIKE'));

        // Not contains / not like
        Assert::assertSame(FilterOperator::NOT_CONTAINS, FilterOperator::fromString('not_contains'));
        Assert::assertSame(FilterOperator::NOT_CONTAINS, FilterOperator::fromString('NOT_LIKE'));

        // Starts with
        Assert::assertSame(FilterOperator::STARTS_WITH, FilterOperator::fromString('starts_with'));
        Assert::assertSame(FilterOperator::STARTS_WITH, FilterOperator::fromString('BEGINS_WITH'));

        // Ends with
        Assert::assertSame(FilterOperator::ENDS_WITH, FilterOperator::fromString('ends_with'));

        // In / Not in
        Assert::assertSame(FilterOperator::IN, FilterOperator::fromString('in'));
        Assert::assertSame(FilterOperator::NOT_IN, FilterOperator::fromString('not_in'));

        // Null / Not null
        Assert::assertSame(FilterOperator::IS_NULL, FilterOperator::fromString('is_null'));
        Assert::assertSame(FilterOperator::IS_NULL, FilterOperator::fromString('NULL'));
        Assert::assertSame(FilterOperator::IS_NOT_NULL, FilterOperator::fromString('is_not_null'));
        Assert::assertSame(FilterOperator::IS_NOT_NULL, FilterOperator::fromString('NOT_NULL'));
    }

    #[Test]
    public function from_string_invalid_value_throws(): void
    {
        // Arrange
        $invalid = 'between';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid filter operator: '.$invalid);

        // Act
        FilterOperator::fromString($invalid);
    }

    #[Test]
    public function requires_value_predicate_is_correct(): void
    {
        // Operators that do not require a value
        Assert::assertFalse(FilterOperator::IS_NULL->requiresValue());
        Assert::assertFalse(FilterOperator::IS_NOT_NULL->requiresValue());

        // All others do require a value
        Assert::assertTrue(FilterOperator::EQUALS->requiresValue());
        Assert::assertTrue(FilterOperator::NOT_EQUALS->requiresValue());
        Assert::assertTrue(FilterOperator::GREATER_THAN->requiresValue());
        Assert::assertTrue(FilterOperator::GREATER_THAN_OR_EQUAL->requiresValue());
        Assert::assertTrue(FilterOperator::LESS_THAN->requiresValue());
        Assert::assertTrue(FilterOperator::LESS_THAN_OR_EQUAL->requiresValue());
        Assert::assertTrue(FilterOperator::CONTAINS->requiresValue());
        Assert::assertTrue(FilterOperator::NOT_CONTAINS->requiresValue());
        Assert::assertTrue(FilterOperator::STARTS_WITH->requiresValue());
        Assert::assertTrue(FilterOperator::ENDS_WITH->requiresValue());
        Assert::assertTrue(FilterOperator::IN->requiresValue());
        Assert::assertTrue(FilterOperator::NOT_IN->requiresValue());
    }

    #[Test]
    public function supports_array_value_predicate_is_correct(): void
    {
        // Only IN and NOT_IN support array values
        Assert::assertTrue(FilterOperator::IN->supportsArrayValue());
        Assert::assertTrue(FilterOperator::NOT_IN->supportsArrayValue());

        Assert::assertFalse(FilterOperator::EQUALS->supportsArrayValue());
        Assert::assertFalse(FilterOperator::NOT_EQUALS->supportsArrayValue());
        Assert::assertFalse(FilterOperator::GREATER_THAN->supportsArrayValue());
        Assert::assertFalse(FilterOperator::GREATER_THAN_OR_EQUAL->supportsArrayValue());
        Assert::assertFalse(FilterOperator::LESS_THAN->supportsArrayValue());
        Assert::assertFalse(FilterOperator::LESS_THAN_OR_EQUAL->supportsArrayValue());
        Assert::assertFalse(FilterOperator::CONTAINS->supportsArrayValue());
        Assert::assertFalse(FilterOperator::NOT_CONTAINS->supportsArrayValue());
        Assert::assertFalse(FilterOperator::STARTS_WITH->supportsArrayValue());
        Assert::assertFalse(FilterOperator::ENDS_WITH->supportsArrayValue());
        Assert::assertFalse(FilterOperator::IS_NULL->supportsArrayValue());
        Assert::assertFalse(FilterOperator::IS_NOT_NULL->supportsArrayValue());
    }

    #[Test]
    public function backed_values_are_correct(): void
    {
        Assert::assertSame('eq', FilterOperator::EQUALS->value);
        Assert::assertSame('ne', FilterOperator::NOT_EQUALS->value);
        Assert::assertSame('gt', FilterOperator::GREATER_THAN->value);
        Assert::assertSame('gte', FilterOperator::GREATER_THAN_OR_EQUAL->value);
        Assert::assertSame('lt', FilterOperator::LESS_THAN->value);
        Assert::assertSame('lte', FilterOperator::LESS_THAN_OR_EQUAL->value);
        Assert::assertSame('contains', FilterOperator::CONTAINS->value);
        Assert::assertSame('not_contains', FilterOperator::NOT_CONTAINS->value);
        Assert::assertSame('starts_with', FilterOperator::STARTS_WITH->value);
        Assert::assertSame('ends_with', FilterOperator::ENDS_WITH->value);
        Assert::assertSame('in', FilterOperator::IN->value);
        Assert::assertSame('not_in', FilterOperator::NOT_IN->value);
        Assert::assertSame('is_null', FilterOperator::IS_NULL->value);
        Assert::assertSame('is_not_null', FilterOperator::IS_NOT_NULL->value);
    }
}
