<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListResponse::class)]
#[UsesClass(PaginationParameters::class)]
final class ListResponseTest extends TestCase
{
    #[Test]
    public function constructs_and_exposes_values(): void
    {
        // Arrange
        $items = [1, 2, 3];
        $total = 45;
        $pagination = new PaginationParameters(page: 2, limit: 20);
        $metadata = ['foo' => 'bar'];

        // Act
        $response = new ListResponse($items, $total, $pagination, $metadata);

        // Assert
        Assert::assertSame($items, $response->getItems());
        Assert::assertSame($total, $response->getTotalItems());
        Assert::assertSame($pagination, $response->getPagination());
        Assert::assertSame($metadata, $response->getMetadata());

        // Derived
        Assert::assertSame(3, $response->getTotalPages()); // ceil(45/20)
        Assert::assertTrue($response->hasPreviousPage());  // page = 2
        Assert::assertTrue($response->hasNextPage());      // 20 < 45 - 20 offset
    }

    #[Test]
    public function create_factory_builds_identical_instance(): void
    {
        // Arrange
        $items = [['id' => 10], ['id' => 11]];
        $total = 25;
        $pagination = new PaginationParameters(page: 3, limit: 10);
        $metadata = ['source' => 'unit-test'];

        // Act
        $response = ListResponse::create($items, $total, $pagination, $metadata);

        // Assert
        Assert::assertSame($items, $response->getItems());
        Assert::assertSame($total, $response->getTotalItems());
        Assert::assertSame($pagination, $response->getPagination());
        Assert::assertSame($metadata, $response->getMetadata());
        Assert::assertSame(3, $response->getTotalPages()); // ceil(25/10)
        Assert::assertTrue($response->hasPreviousPage());  // page 3
        Assert::assertFalse($response->hasNextPage());     // last page for total 25, limit 10
    }

    #[Test]
    public function to_array_returns_expected_structure(): void
    {
        // Arrange
        $items = ['a', 'b'];
        $total = 12;
        $pagination = new PaginationParameters(page: 1, limit: 5);
        $metadata = ['ctx' => ['k' => 'v']];

        $response = new ListResponse($items, $total, $pagination, $metadata);

        // Act
        $arr = $response->toArray();

        // Assert top-level keys
        Assert::assertArrayHasKey('items', $arr);
        Assert::assertArrayHasKey('pagination', $arr);
        Assert::assertArrayHasKey('metadata', $arr);
        Assert::assertSame($items, $arr['items']);
        Assert::assertSame($metadata, $arr['metadata']);

        // Assert pagination payload
        Assert::assertSame(1, $arr['pagination']['page']);
        Assert::assertSame(5, $arr['pagination']['limit']);
        Assert::assertSame($total, $arr['pagination']['total']);
        Assert::assertSame($total, $arr['pagination']['totalItems']);
        Assert::assertSame(3, $arr['pagination']['totalPages']); // ceil(12/5)
        Assert::assertTrue($arr['pagination']['hasNextPage']);
        Assert::assertFalse($arr['pagination']['hasPreviousPage']);
    }

    #[Test]
    public function empty_items_and_default_metadata_work(): void
    {
        // Arrange
        $pagination = new PaginationParameters(page: 1, limit: 10);

        // Act
        $response = new ListResponse([], 0, $pagination);

        // Assert
        Assert::assertSame([], $response->getItems());
        Assert::assertSame(0, $response->getTotalItems());
        Assert::assertSame([], $response->getMetadata());
        Assert::assertSame(0, $response->getTotalPages());
        Assert::assertFalse($response->hasNextPage());
        Assert::assertFalse($response->hasPreviousPage());
    }
}
