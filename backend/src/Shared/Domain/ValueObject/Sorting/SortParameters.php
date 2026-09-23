<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject\Sorting;

/**
 * @psalm-immutable
 */
final readonly class SortParameters
{
    public function __construct(
        public SortField $field,
        public SortDirection $direction = SortDirection::ASC,
    ) {
    }

    /**
     * @param array<string> $allowedFields
     */
    public static function fromRequest(
        ?string $sortBy = null,
        ?string $sortDirection = null,
        array $allowedFields = [],
    ): ?self {
        if (null === $sortBy) {
            return null;
        }

        return new self(
            field: SortField::fromString($sortBy, $allowedFields),
            direction: null !== $sortDirection
                ? SortDirection::fromString($sortDirection)
                : SortDirection::ASC,
        );
    }

    public function getField(): string
    {
        return $this->field->getField();
    }

    public function getDirection(): SortDirection
    {
        return $this->direction;
    }

    public function isAscending(): bool
    {
        return $this->direction->isAscending();
    }

    public function isDescending(): bool
    {
        return $this->direction->isDescending();
    }
}
