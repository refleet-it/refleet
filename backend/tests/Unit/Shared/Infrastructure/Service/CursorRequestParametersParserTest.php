<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Service;

use App\Shared\Domain\Exception\InvalidFilterCriteriaException;
use App\Shared\Domain\Exception\InvalidSortFieldException;
use App\Shared\Domain\ValueObject\CursorListParameters;
use App\Shared\Domain\ValueObject\Filtering\FilterOperator;
use App\Shared\Domain\ValueObject\Sorting\SortDirection;
use App\Shared\Infrastructure\Service\CursorRequestParametersParser;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(CursorRequestParametersParser::class)]
#[UsesClass(CursorListParameters::class)]
final class CursorRequestParametersParserTest extends TestCase
{
    #[Test]
    public function parse_from_request_with_defaults(): void
    {
        // Arrange
        $request = Request::create('/items', 'GET');
        $parser = new CursorRequestParametersParser();

        // Act
        $params = $parser->parseFromRequest($request);

        // Assert
        Assert::assertNull($params->getPagination()->getCursor());
        Assert::assertSame(6, $params->getPagination()->getLimit());
        Assert::assertFalse($params->hasSorting());
        Assert::assertFalse($params->hasFiltering());
        Assert::assertSame(0, $params->getFiltering()->count());
    }

    #[Test]
    public function parse_from_request_with_valid_params_and_simple_filters(): void
    {
        // Arrange
        $encodedCursor = \base64_encode(\json_encode(['id' => 'cursor-123']));
        $request = Request::create('/items', 'GET', [
            'cursor' => $encodedCursor,
            'limit' => '50',
            'sortBy' => 'name',
            'sortDirection' => 'desc',
            'status' => 'active',
            'category' => 'books',
        ]);

        $parser = CursorRequestParametersParser::create(
            allowedSortFields: ['name'],
            allowedFilterFields: ['status', 'category'],
        );

        // Act
        $params = $parser->parseFromRequest($request);

        // Assert pagination
        Assert::assertSame('cursor-123', $params->getPagination()->getCursor());
        Assert::assertSame(50, $params->getPagination()->getLimit());

        // Assert sorting
        Assert::assertTrue($params->hasSorting());
        Assert::assertNotNull($params->getSorting());
        Assert::assertSame('name', $params->getSorting()->getField());
        Assert::assertSame(SortDirection::DESC, $params->getSorting()->getDirection());
        Assert::assertTrue($params->getSorting()->isDescending());

        // Assert filtering
        Assert::assertTrue($params->hasFiltering());
        Assert::assertSame(2, $params->getFiltering()->count());
        $statusFilters = $params->getFiltering()->getByField('status');
        $categoryFilters = $params->getFiltering()->getByField('category');
        Assert::assertCount(1, $statusFilters);
        Assert::assertCount(1, $categoryFilters);
        $status = \array_first($statusFilters);
        $category = \array_first($categoryFilters);
        Assert::assertSame(FilterOperator::EQUALS, $status->getOperator());
        Assert::assertSame('active', $status->getValue());
        Assert::assertSame(FilterOperator::EQUALS, $category->getOperator());
        Assert::assertSame('books', $category->getValue());
    }

    #[Test]
    public function parse_from_request_with_complex_filters(): void
    {
        // Arrange
        $request = Request::create('/items', 'GET', [
            'filters' => [
                'name' => ['contains' => 'abc'],
                'price' => ['gte' => '10'],
            ],
            'tag' => 'new',
        ]);

        $parser = CursorRequestParametersParser::create(
            allowedFilterFields: ['name', 'price', 'tag'],
        );

        // Act
        $params = $parser->parseFromRequest($request);

        // Assert
        Assert::assertTrue($params->hasFiltering());
        Assert::assertSame(3, $params->getFiltering()->count());

        $nameCriteria = \array_values($params->getFiltering()->getByField('name'));
        $priceCriteria = \array_values($params->getFiltering()->getByField('price'));
        $tagCriteria = \array_values($params->getFiltering()->getByField('tag'));

        Assert::assertCount(1, $nameCriteria);
        Assert::assertCount(1, $priceCriteria);
        Assert::assertCount(1, $tagCriteria);

        Assert::assertSame(FilterOperator::CONTAINS, $nameCriteria[0]->getOperator());
        Assert::assertSame('abc', $nameCriteria[0]->getValue());

        Assert::assertSame(FilterOperator::GREATER_THAN_OR_EQUAL, $priceCriteria[0]->getOperator());
        Assert::assertSame('10', $priceCriteria[0]->getValue());

        Assert::assertSame(FilterOperator::EQUALS, $tagCriteria[0]->getOperator());
        Assert::assertSame('new', $tagCriteria[0]->getValue());
    }

    #[Test]
    public function parse_from_request_invalid_sort_field_throws(): void
    {
        // Arrange
        $request = Request::create('/items', 'GET', [
            'sortBy' => 'invalid_field',
            'sortDirection' => 'asc',
        ]);

        $parser = CursorRequestParametersParser::create(
            allowedSortFields: ['name', 'price'],
        );

        // Assert
        $this->expectException(InvalidSortFieldException::class);

        // Act
        $parser->parseFromRequest($request);
    }

    #[Test]
    public function parse_from_request_invalid_sort_direction_throws(): void
    {
        // Arrange
        $request = Request::create('/items', 'GET', [
            'sortBy' => 'name',
            'sortDirection' => 'invalid',
        ]);

        $parser = CursorRequestParametersParser::create(
            allowedSortFields: ['name'],
        );

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $parser->parseFromRequest($request);
    }

    #[Test]
    public function parse_from_request_invalid_filter_field_throws(): void
    {
        // Arrange
        $request = Request::create('/items', 'GET', [
            'category' => 'books',
        ]);

        $parser = CursorRequestParametersParser::create(
            allowedFilterFields: ['status'],
        );

        // Assert
        $this->expectException(InvalidFilterCriteriaException::class);

        // Act
        $parser->parseFromRequest($request);
    }

    #[Test]
    public function parse_simple_from_request_ignores_filters(): void
    {
        // Arrange
        $encodedCursor = \base64_encode(\json_encode(['id' => 'abc']));
        $request = Request::create('/items', 'GET', [
            'cursor' => $encodedCursor,
            'limit' => '10',
            'sortBy' => 'name',
            'sortDirection' => 'asc',
            'status' => 'active',
            'filters' => [
                'price' => ['gte' => '100'],
            ],
        ]);

        $parser = new CursorRequestParametersParser();

        // Act
        $params = $parser->parseSimpleFromRequest($request);

        // Assert
        Assert::assertSame('abc', $params->getPagination()->getCursor());
        Assert::assertSame(10, $params->getPagination()->getLimit());
        Assert::assertTrue($params->hasSorting());
        Assert::assertFalse($params->hasFiltering());
        Assert::assertTrue($params->getFiltering()->isEmpty());
    }

    #[Test]
    public function with_allowed_fields_creates_new_instances_and_applies_restrictions(): void
    {
        // Arrange
        $original = new CursorRequestParametersParser();
        $restricted = $original
            ->withAllowedSortFields(['name'])
            ->withAllowedFilterFields(['status']);

        // Original: unrestricted sort field and filter field should be accepted
        $reqOriginal = Request::create('/items', 'GET', [
            'sortBy' => 'price',
            'sortDirection' => 'asc',
            'category' => 'books',
        ]);

        // Act + Assert: original works
        $paramsOriginal = $original->parseFromRequest($reqOriginal);
        Assert::assertSame('price', $paramsOriginal->getSorting()?->getField());
        Assert::assertTrue($paramsOriginal->hasFiltering());

        // Restricted: invalid sort field and filter field should throw
        $reqRestricted = Request::create('/items', 'GET', [
            'sortBy' => 'price',
            'sortDirection' => 'asc',
            'category' => 'books',
        ]);

        $this->expectException(InvalidSortFieldException::class);
        $restricted->parseFromRequest($reqRestricted);
    }
}
