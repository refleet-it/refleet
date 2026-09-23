<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;

/**
 * @template T
 */
final readonly class ListResponse
{
    /**
     * @param T[]                  $items
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $items,
        public int $totalItems,
        public PaginationParameters $pagination,
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
        int $totalItems,
        PaginationParameters $pagination,
        array $metadata = [],
    ): self {
        return new self($items, $totalItems, $pagination, $metadata);
    }

    /**
     * @return T[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getPagination(): PaginationParameters
    {
        return $this->pagination;
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
                'page' => $this->pagination->getPage(),
                'limit' => $this->pagination->getLimit(),
                'total' => $this->totalItems,
                'totalItems' => $this->totalItems,
                'totalPages' => $this->getTotalPages(),
                'hasNextPage' => $this->hasNextPage(),
                'hasPreviousPage' => $this->hasPreviousPage(),
            ],
            'metadata' => $this->metadata,
        ];
    }

    public function getTotalPages(): int
    {
        return $this->pagination->getTotalPages($this->totalItems);
    }

    public function hasNextPage(): bool
    {
        return $this->pagination->hasNextPage($this->totalItems);
    }

    public function hasPreviousPage(): bool
    {
        return $this->pagination->hasPreviousPage();
    }
}
