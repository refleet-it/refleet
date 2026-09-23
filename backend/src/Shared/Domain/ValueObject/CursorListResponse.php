<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Pagination\CursorPaginationParameters;

/**
 * @template T
 */
final readonly class CursorListResponse
{
    /**
     * @param T[]                  $items
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $items,
        public CursorPaginationParameters $pagination,
        public ?string $nextCursor = null,
        public bool $hasNextPage = false,
        public array $metadata = [],
    ) {
    }

    /**
     * @template U
     *
     * @param U[]                  $items
     * @param array<string, mixed> $metadata
     *
     * @return self<U>
     */
    public static function create(
        array $items,
        CursorPaginationParameters $pagination,
        ?string $nextCursor = null,
        bool $hasNextPage = false,
        array $metadata = [],
    ): self {
        return new self($items, $pagination, $nextCursor, $hasNextPage, $metadata);
    }

    /**
     * @return T[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getPagination(): CursorPaginationParameters
    {
        return $this->pagination;
    }

    public function getNextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function hasNextPage(): bool
    {
        return $this->hasNextPage;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'pagination' => [
                'cursor' => $this->pagination->getCursor(),
                'limit' => $this->pagination->getLimit(),
                'nextCursor' => $this->nextCursor,
                'hasNextPage' => $this->hasNextPage,
            ],
            'metadata' => $this->metadata,
        ];
    }
}
