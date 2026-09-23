<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Filtering\FilterOperator;
use App\Shared\Domain\ValueObject\Filtering\FilterParameters;
use App\Shared\Domain\ValueObject\ListParameters;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortDirection;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListParameters::class)]
#[UsesClass(PaginationParameters::class)]
#[UsesClass(SortParameters::class)]
#[UsesClass(FilterParameters::class)]
final class ListParametersTest extends TestCase
{
    #[Test]
    public function simple_with_defaults(): void
    {
        // Act
        $params = ListParameters::simple();

        // Assert
        Assert::assertSame(1, $params->getPagination()->getPage());
        Assert::assertSame(20, $params->getPagination()->getLimit());
        Assert::assertFalse($params->hasSorting());
        Assert::assertNull($params->getSorting());
        Assert::assertFalse($params->hasFiltering());
        Assert::assertTrue($params->getFiltering()->isEmpty());
        Assert::assertSame(0, $params->getFiltering()->count());
    }

    #[Test]
    public function simple_with_values_sets_pagination_and_sorting(): void
    {
        // Act
        $params = ListParameters::simple(page: 3, limit: 10, sortBy: 'name', sortDirection: 'desc');

        // Assert pagination
        Assert::assertSame(3, $params->getPagination()->getPage());
        Assert::assertSame(10, $params->getPagination()->getLimit());

        // Assert sorting
        Assert::assertTrue($params->hasSorting());
        Assert::assertNotNull($params->getSorting());
        Assert::assertSame('name', $params->getSorting()->getField());
        Assert::assertSame(SortDirection::DESC, $params->getSorting()->getDirection());
        Assert::assertTrue($params->getSorting()->isDescending());

        // Filtering stays empty in simple()
        Assert::assertFalse($params->hasFiltering());
        Assert::assertTrue($params->getFiltering()->isEmpty());
    }

    #[Test]
    public function from_request_with_allowed_fields_and_filters(): void
    {
        // Arrange
        $filters = [
            // simple format (defaults to equals)
            'status' => 'active',
            // complex format
            'price' => ['gte' => '10'],
            'tag' => ['contains' => 'new'],
        ];

        // Act
        $params = ListParameters::fromRequest(
            page: 2,
            limit: 5,
            sortBy: 'price',
            sortDirection: 'asc',
            filters: $filters,
            allowedSortFields: ['price'],
            allowedFilterFields: ['status', 'price', 'tag'],
        );

        // Assert pagination
        Assert::assertSame(2, $params->getPagination()->getPage());
        Assert::assertSame(5, $params->getPagination()->getLimit());

        // Assert sorting
        Assert::assertTrue($params->hasSorting());
        Assert::assertNotNull($params->getSorting());
        Assert::assertSame('price', $params->getSorting()->getField());
        Assert::assertSame(SortDirection::ASC, $params->getSorting()->getDirection());
        Assert::assertTrue($params->getSorting()->isAscending());

        // Assert filtering
        Assert::assertTrue($params->hasFiltering());
        Assert::assertSame(3, $params->getFiltering()->count());

        $statusCriteria = \array_values($params->getFiltering()->getByField('status'));
        $priceCriteria = \array_values($params->getFiltering()->getByField('price'));
        $tagCriteria = \array_values($params->getFiltering()->getByField('tag'));

        Assert::assertCount(1, $statusCriteria);
        Assert::assertCount(1, $priceCriteria);
        Assert::assertCount(1, $tagCriteria);

        Assert::assertSame(FilterOperator::EQUALS, $statusCriteria[0]->getOperator());
        Assert::assertSame('active', $statusCriteria[0]->getValue());
        Assert::assertSame(FilterOperator::GREATER_THAN_OR_EQUAL, $priceCriteria[0]->getOperator());
        Assert::assertSame('10', $priceCriteria[0]->getValue());
        Assert::assertSame(FilterOperator::CONTAINS, $tagCriteria[0]->getOperator());
        Assert::assertSame('new', $tagCriteria[0]->getValue());
    }

    #[Test]
    public function with_filtering_returns_new_instance_and_preserves_others(): void
    {
        // Arrange
        $original = ListParameters::simple(page: 1, limit: 20);
        $newFiltering = FilterParameters::fromRequest(['status' => 'active'], ['status']);

        // Act
        $updated = $original->withFiltering($newFiltering);

        // Assert: a new instance is returned
        Assert::assertNotSame($original, $updated);

        // Assert: filtering replaced with the provided instance
        Assert::assertSame($newFiltering, $updated->getFiltering());
        Assert::assertTrue($updated->hasFiltering());
        Assert::assertFalse($original->hasFiltering());

        // Assert: pagination and sorting are preserved
        Assert::assertSame($original->getPagination(), $updated->getPagination());
        Assert::assertSame($original->getSorting(), $updated->getSorting());
    }
}
