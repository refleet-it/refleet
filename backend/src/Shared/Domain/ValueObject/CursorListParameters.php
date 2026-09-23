<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Filtering\FilterParameters;
use App\Shared\Domain\ValueObject\Pagination\CursorPaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;

final readonly class CursorListParameters
{
    public function __construct(
        public CursorPaginationParameters $pagination,
        public ?SortParameters $sorting = null,
        public FilterParameters $filtering = new FilterParameters(),
    ) {
    }

    public static function simple(
        ?string $cursor = null,
        ?int $limit = null,
        ?string $sortBy = null,
        ?string $sortDirection = null,
    ): self {
        return new self(
            pagination: CursorPaginationParameters::fromRequest($cursor, $limit),
            sorting: SortParameters::fromRequest($sortBy, $sortDirection),
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string>        $allowedSortFields
     * @param array<string>        $allowedFilterFields
     */
    public static function fromRequest(
        ?string $cursor = null,
        ?int $limit = null,
        ?string $sortBy = null,
        ?string $sortDirection = null,
        array $filters = [],
        array $allowedSortFields = [],
        array $allowedFilterFields = [],
    ): self {
        return new self(
            pagination: CursorPaginationParameters::fromRequest($cursor, $limit),
            sorting: SortParameters::fromRequest($sortBy, $sortDirection, $allowedSortFields),
            filtering: FilterParameters::fromRequest($filters, $allowedFilterFields),
        );
    }

    public function getPagination(): CursorPaginationParameters
    {
        return $this->pagination;
    }

    public function getSorting(): ?SortParameters
    {
        return $this->sorting;
    }

    public function getFiltering(): FilterParameters
    {
        return $this->filtering;
    }

    public function hasSorting(): bool
    {
        return null !== $this->sorting;
    }

    public function hasFiltering(): bool
    {
        return !$this->filtering->isEmpty();
    }

    public function withFiltering(FilterParameters $filtering): self
    {
        return new self(
            pagination: $this->pagination,
            sorting: $this->sorting,
            filtering: $filtering,
        );
    }
}
