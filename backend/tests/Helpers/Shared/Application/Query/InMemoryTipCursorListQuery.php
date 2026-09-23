<?php

declare(strict_types=1);

namespace App\Tests\Helpers\Shared\Application\Query;

use App\Shared\Application\Query\AbstractCursorListQuery;
use App\Shared\Domain\ValueObject\CursorListParameters;

final readonly class InMemoryTipCursorListQuery extends AbstractCursorListQuery
{
    /**
     * @param CursorListItemStub[] $items
     */
    public function __construct(
        private array $items,
    ) {
    }

    #[\Override]
    protected function fetchItems(CursorListParameters $parameters): array
    {
        return $this->items;
    }
}
