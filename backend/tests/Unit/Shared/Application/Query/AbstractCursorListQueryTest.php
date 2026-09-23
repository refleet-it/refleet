<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Query;

use App\Shared\Application\Query\AbstractCursorListQuery;
use App\Shared\Domain\ValueObject\CursorListParameters;
use App\Tests\Helpers\Shared\Application\Query\CursorListItemStub;
use App\Tests\Helpers\Shared\Application\Query\InMemoryTipCursorListQuery;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractCursorListQuery::class)]
final class AbstractCursorListQueryTest extends TestCase
{
    #[Test]
    public function get_list_applies_filtering_sorting_and_cursor_from_trimmed_items(): void
    {
        // Arrange
        $alphaOne = new CursorListItemStub('alpha-1');
        $alphaTwo = new CursorListItemStub('alpha-2');
        $beta = new CursorListItemStub('beta');
        $query = new InMemoryTipCursorListQuery([$beta, $alphaTwo, $alphaOne]);
        $parameters = CursorListParameters::fromRequest(
            limit: 1,
            sortBy: 'content',
            sortDirection: 'asc',
            filters: ['content' => ['contains' => 'alpha']],
            allowedSortFields: ['content'],
            allowedFilterFields: ['content'],
        );

        // Act
        $result = $query->getList($parameters);

        // Assert
        Assert::assertCount(1, $result->getItems());
        Assert::assertSame($alphaOne->id()->asString(), $result->getItems()[0]->id()->asString());
        Assert::assertTrue($result->hasNextPage());
        Assert::assertNotNull($result->getNextCursor());
        Assert::assertSame($alphaOne->id()->asString(), $this->decodeCursorId($result->getNextCursor()));
    }

    #[Test]
    public function get_list_marks_no_next_page_when_item_count_equals_limit(): void
    {
        // Arrange
        $one = new CursorListItemStub('a');
        $two = new CursorListItemStub('b');
        $query = new InMemoryTipCursorListQuery([$two, $one]);
        $parameters = CursorListParameters::fromRequest(
            limit: 2,
            sortBy: 'content',
            sortDirection: 'asc',
            allowedSortFields: ['content'],
        );

        // Act
        $result = $query->getList($parameters);

        // Assert
        Assert::assertCount(2, $result->getItems());
        Assert::assertFalse($result->hasNextPage());
        Assert::assertSame($two->id()->asString(), $this->decodeCursorId($result->getNextCursor()));
    }

    #[Test]
    public function get_all_trims_extra_item_after_cursor_pagination(): void
    {
        // Arrange
        $first = new CursorListItemStub('a');
        $second = new CursorListItemStub('b');
        $third = new CursorListItemStub('c');
        $query = new InMemoryTipCursorListQuery([$third, $first, $second]);
        $parameters = CursorListParameters::fromRequest(
            limit: 2,
            sortBy: 'content',
            sortDirection: 'asc',
            allowedSortFields: ['content'],
        );

        // Act
        $items = $query->getAll($parameters);

        // Assert
        Assert::assertCount(2, $items);
        Assert::assertSame($first->id()->asString(), $items[0]->id()->asString());
        Assert::assertSame($second->id()->asString(), $items[1]->id()->asString());
    }

    #[Test]
    public function filtering_with_missing_field_excludes_items(): void
    {
        // Arrange
        $item = new CursorListItemStub('alpha');
        $query = new InMemoryTipCursorListQuery([$item]);
        $parameters = CursorListParameters::fromRequest(
            filters: ['unknownField' => 'alpha'],
        );

        // Act
        $items = $query->getAll($parameters);

        // Assert
        Assert::assertSame([], $items);
    }

    private function decodeCursorId(?string $cursor): ?string
    {
        if (null === $cursor) {
            return null;
        }

        $decoded = \base64_decode($cursor, true);
        if (false === $decoded) {
            return null;
        }

        $cursorPayload = \json_decode($decoded, true);
        if (!\is_array($cursorPayload)) {
            return null;
        }

        $id = $cursorPayload['id'] ?? null;

        return \is_string($id) ? $id : null;
    }
}
