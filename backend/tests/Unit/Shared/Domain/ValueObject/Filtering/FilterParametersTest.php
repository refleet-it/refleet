<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject\Filtering;

use App\Shared\Domain\Exception\InvalidFilterCriteriaException;
use App\Shared\Domain\ValueObject\Filtering\FilterCriteria;
use App\Shared\Domain\ValueObject\Filtering\FilterOperator;
use App\Shared\Domain\ValueObject\Filtering\FilterParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilterParameters::class)]
#[UsesClass(FilterCriteria::class)]
#[UsesClass(FilterOperator::class)]
final class FilterParametersTest extends TestCase
{
    #[Test]
    public function from_request_builds_criteria_from_simple_and_complex_filters_and_ignores_unsupported_shapes(): void
    {
        // Arrange
        $filters = [
            'status' => 'active',
            'name' => ['contains' => 'john'],
            'age' => ['gte' => 18, 'lte' => 65],
            'ignored' => 123,
        ];

        // Act
        $parameters = FilterParameters::fromRequest($filters, ['status', 'name', 'age']);

        // Assert
        Assert::assertFalse($parameters->isEmpty());
        Assert::assertSame(4, $parameters->count());

        $criteria = \array_values($parameters->getCriteria());
        Assert::assertCount(4, $criteria);

        Assert::assertSame('status', $criteria[0]->getField());
        Assert::assertSame(FilterOperator::EQUALS, $criteria[0]->getOperator());
        Assert::assertSame('active', $criteria[0]->getValue());

        Assert::assertSame('name', $criteria[1]->getField());
        Assert::assertSame(FilterOperator::CONTAINS, $criteria[1]->getOperator());
        Assert::assertSame('john', $criteria[1]->getValue());

        Assert::assertSame('age', $criteria[2]->getField());
        Assert::assertSame(FilterOperator::GREATER_THAN_OR_EQUAL, $criteria[2]->getOperator());
        Assert::assertSame(18, $criteria[2]->getValue());

        Assert::assertSame('age', $criteria[3]->getField());
        Assert::assertSame(FilterOperator::LESS_THAN_OR_EQUAL, $criteria[3]->getOperator());
        Assert::assertSame(65, $criteria[3]->getValue());
    }

    #[Test]
    public function from_request_propagates_validation_error_for_disallowed_field(): void
    {
        // Arrange
        $filters = ['status' => 'active'];

        // Act
        try {
            FilterParameters::fromRequest($filters, ['name']);
            Assert::fail('Expected InvalidFilterCriteriaException to be thrown.');
        } catch (InvalidFilterCriteriaException $invalidFilterCriteriaException) {
            // Assert
            Assert::assertSame('Filter field "status" is not allowed. Allowed fields: name', $invalidFilterCriteriaException->getMessage());
        }
    }

    #[Test]
    public function empty_and_add_keep_immutability_and_count_consistent(): void
    {
        // Arrange
        $initial = FilterParameters::empty();
        $criteria = FilterCriteria::fromRequest('status', 'eq', 'active', ['status']);

        // Act
        $updated = $initial->add($criteria);

        // Assert
        Assert::assertTrue($initial->isEmpty());
        Assert::assertSame(0, $initial->count());

        Assert::assertFalse($updated->isEmpty());
        Assert::assertSame(1, $updated->count());
        Assert::assertSame([$criteria], \array_values($updated->getCriteria()));
    }

    #[Test]
    public function get_by_field_returns_only_matching_criteria(): void
    {
        // Arrange
        $parameters = FilterParameters::fromRequest([
            'status' => ['eq' => 'active', 'ne' => 'archived'],
            'name' => ['contains' => 'jo'],
        ], ['status', 'name']);

        // Act
        $statusCriteria = \array_values($parameters->getByField('status'));
        $missingCriteria = $parameters->getByField('missing');

        // Assert
        Assert::assertCount(2, $statusCriteria);
        Assert::assertSame(FilterOperator::EQUALS, $statusCriteria[0]->getOperator());
        Assert::assertSame(FilterOperator::NOT_EQUALS, $statusCriteria[1]->getOperator());
        Assert::assertSame('active', $statusCriteria[0]->getValue());
        Assert::assertSame('archived', $statusCriteria[1]->getValue());
        Assert::assertSame([], $missingCriteria);
    }
}
