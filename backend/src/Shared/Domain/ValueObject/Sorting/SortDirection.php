<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject\Sorting;

enum SortDirection: string
{
    public static function fromString(string $direction): self
    {
        return match (\strtolower($direction)) {
            'asc', 'ascending' => self::ASC,
            'desc', 'descending' => self::DESC,
            default => throw new \InvalidArgumentException('Invalid sort direction: '.$direction),
        };
    }

    public function isAscending(): bool
    {
        return self::ASC === $this;
    }

    public function isDescending(): bool
    {
        return self::DESC === $this;
    }

    case ASC = 'asc';
    case DESC = 'desc';
}
