<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidFilterCriteriaException;
use App\Shared\Domain\Exception\InvalidSortFieldException;
use App\Shared\Domain\ValueObject\CursorListParameters;
use App\Shared\Domain\ValueObject\Filtering\FilterOperator;
use App\Shared\Domain\ValueObject\Filtering\FilterParameters;
use App\Shared\Domain\ValueObject\Pagination\CursorPaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortDirection;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CursorListParameters::class)]
#[UsesClass(CursorPaginationParameters::class)]
#[UsesClass(SortParameters::class)]
#[UsesClass(FilterParameters::class)]
final class CursorListParametersTest extends TestCase
{
    #[Test]
    public function simple_with_defaults(): void
    {
        // Act
        $params = CursorListParameters::simple();

        // Assert
        Assert::assertNull($params->getPagination()->getCursor());
        Assert::assertSame(6, $params->getPagination()->getLimit());
        Assert::assertFalse($params->hasSorting());
        Assert::assertNull($params->getSorting());
        Assert::assertFalse($params->hasFiltering());
        Assert::assertTrue($params->getFiltering()->isEmpty());
        Assert::assertSame(0, $params->getFiltering()->count());
    }

    #[Test]
    public function simple_with_values_sets_pagination_and_sorting(): void
    {
        // Arrange
        $cursor = \base64_encode((string) \json_encode(['id' => 'cursor-123']));

        // Act
        $params = CursorListParameters::simple(
            cursor: $cursor,
            limit: 25,
            sortBy: 'createdAt',
            sortDirection: 'desc',
        );

        // Assert
        Assert::assertSame('cursor-123', $params->getPagination()->getCursor());
        Assert::assertSame(25, $params->getPagination()->getLimit());
        Assert::assertTrue($params->hasSorting());
        Assert::assertNotNull($params->getSorting());
        Assert::assertSame('createdAt', $params->getSorting()->getField());
        Assert::assertSame(SortDirection::DESC, $params->getSorting()->getDirection());
        Assert::assertTrue($params->getSorting()->isDescending());
        Assert::assertFalse($params->hasFiltering());
    }

    #[Test]
    public function from_request_with_allowed_fields_and_filters(): void
    {
        // Arrange
        $filters = [
            'status' => 'active',
            'price' => ['gte' => '10'],
        ];

        // Act
        $params = CursorListParameters::fromRequest(
            cursor: null,
            limit: 12,
            sortBy: 'price',
            sortDirection: 'asc',
            filters: $filters,
            allowedSortFields: ['price'],
            allowedFilterFields: ['status', 'price'],
        );

        // Assert
        Assert::assertSame(12, $params->getPagination()->getLimit());
        Assert::assertTrue($params->hasSorting());
        Assert::assertNotNull($params->getSorting());
        Assert::assertSame('price', $params->getSorting()->getField());
        Assert::assertSame(SortDirection::ASC, $params->getSorting()->getDirection());
        Assert::assertTrue($params->hasFiltering());
        Assert::assertSame(2, $params->getFiltering()->count());
        $statusCriteria = \array_values($params->getFiltering()->getByField('status'));
        $priceCriteria = \array_values($params->getFiltering()->getByField('price'));
        Assert::assertCount(1, $statusCriteria);
        Assert::assertCount(1, $priceCriteria);
        Assert::assertSame(FilterOperator::EQUALS, $statusCriteria[0]->getOperator());
        Assert::assertSame('active', $statusCriteria[0]->getValue());
        Assert::assertSame(FilterOperator::GREATER_THAN_OR_EQUAL, $priceCriteria[0]->getOperator());
        Assert::assertSame('10', $priceCriteria[0]->getValue());
    }

    #[Test]
    public function from_request_invalid_sort_field_throws(): void
    {
        // Arrange
        $thrown = null;

        // Act
        try {
            CursorListParameters::fromRequest(
                sortBy: 'unknown',
                sortDirection: 'asc',
                allowedSortFields: ['name'],
            );
        } catch (\Throwable $throwable) {
            $thrown = $throwable;
        }

        // Assert
        Assert::assertNotNull($thrown);
        Assert::assertInstanceOf(InvalidSortFieldException::class, $thrown);
    }

    #[Test]
    public function from_request_invalid_filter_field_throws(): void
    {
        // Arrange
        $thrown = null;

        // Act
        try {
            CursorListParameters::fromRequest(
                filters: ['category' => 'books'],
                allowedFilterFields: ['status'],
            );
        } catch (\Throwable $throwable) {
            $thrown = $throwable;
        }

        // Assert
        Assert::assertNotNull($thrown);
        Assert::assertInstanceOf(InvalidFilterCriteriaException::class, $thrown);
    }

    #[Test]
    public function with_filtering_returns_new_instance_and_preserves_pagination_and_sorting(): void
    {
        // Arrange
        $original = CursorListParameters::simple(sortBy: 'name', sortDirection: 'asc');
        $filtering = FilterParameters::fromRequest(['status' => 'active'], ['status']);

        // Act
        $updated = $original->withFiltering($filtering);

        // Assert
        Assert::assertNotSame($original, $updated);
        Assert::assertSame($original->getPagination(), $updated->getPagination());
        Assert::assertSame($original->getSorting(), $updated->getSorting());
        Assert::assertSame($filtering, $updated->getFiltering());
        Assert::assertFalse($original->hasFiltering());
        Assert::assertTrue($updated->hasFiltering());
    }
}
