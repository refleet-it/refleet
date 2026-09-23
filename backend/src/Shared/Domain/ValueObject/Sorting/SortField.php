<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject\Sorting;

use App\Shared\Domain\Exception\InvalidSortFieldException;

/**
 * @psalm-immutable
 */
final readonly class SortField
{
    /**
     * @param array<string> $allowedFields
     */
    public function __construct(
        public string $field,
        private array $allowedFields = [],
    ) {
        $this->validate();
    }

    public function isAllowed(): bool
    {
        return [] === $this->allowedFields || \in_array($this->field, $this->allowedFields, true);
    }

    /**
     * @param array<string> $allowedFields
     */
    public static function fromString(string $field, array $allowedFields = []): self
    {
        return new self($field, $allowedFields);
    }

    public function getField(): string
    {
        return $this->field;
    }

    private function validate(): void
    {
        if ('' === $this->field) {
            throw new InvalidSortFieldException('Sort field cannot be empty');
        }

        if ([] !== $this->allowedFields && !$this->isAllowed()) {
            throw new InvalidSortFieldException(\sprintf('Sort field "%s" is not allowed. Allowed fields: %s', $this->field, \implode(', ', $this->allowedFields)));
        }
    }
}
