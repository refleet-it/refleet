<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject\Pagination;

use App\Shared\Domain\Exception\InvalidPaginationParametersException;

final readonly class PaginationParameters
{
    private const int DEFAULT_PAGE = 1;

    private const int DEFAULT_LIMIT = 20;

    private const int MAX_LIMIT = 100;

    private const int MIN_LIMIT = 1;

    public function __construct(
        public int $page = self::DEFAULT_PAGE,
        public int $limit = self::DEFAULT_LIMIT,
    ) {
        $this->validate();
    }

    public static function fromRequest(?int $page = null, ?int $limit = null): self
    {
        return new self(
            page: $page ?? self::DEFAULT_PAGE,
            limit: $limit ?? self::DEFAULT_LIMIT,
        );
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function hasNextPage(int $totalItems): bool
    {
        return $this->getOffset() + $this->limit < $totalItems;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }

    public function getTotalPages(int $totalItems): int
    {
        return (int) \ceil($totalItems / $this->limit);
    }

    private function validate(): void
    {
        if ($this->page < 1) {
            throw new InvalidPaginationParametersException('Page must be greater than 0');
        }

        if ($this->limit < self::MIN_LIMIT || $this->limit > self::MAX_LIMIT) {
            throw new InvalidPaginationParametersException(\sprintf('Limit must be between %d and %d', self::MIN_LIMIT, self::MAX_LIMIT));
        }
    }
}
