<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CursorListResponse;
use App\Shared\Domain\ValueObject\Pagination\CursorPaginationParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CursorListResponse::class)]
#[UsesClass(CursorPaginationParameters::class)]
final class CursorListResponseTest extends TestCase
{
    #[Test]
    public function constructs_and_exposes_all_values(): void
    {
        // Arrange
        $items = [['id' => '1'], ['id' => '2']];
        $pagination = new CursorPaginationParameters(cursor: 'cursor-1', limit: 10);
        $nextCursor = 'next-cursor';
        $metadata = ['source' => 'unit-test'];

        // Act
        $response = new CursorListResponse($items, $pagination, $nextCursor, true, $metadata);

        // Assert
        Assert::assertSame($items, $response->getItems());
        Assert::assertSame($pagination, $response->getPagination());
        Assert::assertSame($nextCursor, $response->getNextCursor());
        Assert::assertTrue($response->hasNextPage());
        Assert::assertSame($metadata, $response->getMetadata());
    }

    #[Test]
    public function create_factory_uses_defaults_when_optional_values_are_not_provided(): void
    {
        // Arrange
        $items = [];
        $pagination = new CursorPaginationParameters();

        // Act
        $response = CursorListResponse::create($items, $pagination);

        // Assert
        Assert::assertSame([], $response->getItems());
        Assert::assertSame($pagination, $response->getPagination());
        Assert::assertNull($response->getNextCursor());
        Assert::assertFalse($response->hasNextPage());
        Assert::assertSame([], $response->getMetadata());
    }

    #[Test]
    public function to_array_returns_expected_shape_with_next_page_values(): void
    {
        // Arrange
        $items = [['id' => '10'], ['id' => '11']];
        $pagination = new CursorPaginationParameters(cursor: 'current-cursor', limit: 25);
        $response = CursorListResponse::create(
            items: $items,
            pagination: $pagination,
            nextCursor: 'encoded-next-cursor',
            hasNextPage: true,
            metadata: ['context' => ['tenant' => 'acme']],
        );

        // Act
        $result = $response->toArray();

        // Assert
        Assert::assertSame($items, $result['items']);
        Assert::assertSame('current-cursor', $result['pagination']['cursor']);
        Assert::assertSame(25, $result['pagination']['limit']);
        Assert::assertSame('encoded-next-cursor', $result['pagination']['nextCursor']);
        Assert::assertTrue($result['pagination']['hasNextPage']);
        Assert::assertSame(['context' => ['tenant' => 'acme']], $result['metadata']);
    }

    #[Test]
    public function to_array_preserves_null_next_cursor_and_false_has_next_page(): void
    {
        // Arrange
        $pagination = new CursorPaginationParameters(cursor: null, limit: 6);
        $response = new CursorListResponse([], $pagination, null, false, []);

        // Act
        $result = $response->toArray();

        // Assert
        Assert::assertSame([], $result['items']);
        Assert::assertNull($result['pagination']['cursor']);
        Assert::assertSame(6, $result['pagination']['limit']);
        Assert::assertNull($result['pagination']['nextCursor']);
        Assert::assertFalse($result['pagination']['hasNextPage']);
        Assert::assertSame([], $result['metadata']);
    }
}
