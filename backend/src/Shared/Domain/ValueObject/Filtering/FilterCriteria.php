<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject\Filtering;

use App\Shared\Domain\Exception\InvalidFilterCriteriaException;

final readonly class FilterCriteria
{
    /**
     * @param array<string> $allowedFields
     */
    public function __construct(
        public string $field,
        public FilterOperator $operator,
        public mixed $value = null,
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
    public static function fromRequest(
        string $field,
        string $operator,
        mixed $value = null,
        array $allowedFields = [],
    ): self {
        return new self(
            field: $field,
            operator: FilterOperator::fromString($operator),
            value: $value,
            allowedFields: $allowedFields,
        );
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getOperator(): FilterOperator
    {
        return $this->operator;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    private function validate(): void
    {
        if ('' === $this->field) {
            throw new InvalidFilterCriteriaException('Filter field cannot be empty');
        }

        if ([] !== $this->allowedFields && !$this->isAllowed()) {
            throw new InvalidFilterCriteriaException(\sprintf('Filter field "%s" is not allowed. Allowed fields: %s', $this->field, \implode(', ', $this->allowedFields)));
        }

        if ($this->operator->requiresValue() && null === $this->value) {
            throw new InvalidFilterCriteriaException(\sprintf('Filter operator "%s" requires a value', $this->operator->value));
        }

        if ($this->operator->supportsArrayValue() && !\is_array($this->value)) {
            throw new InvalidFilterCriteriaException(\sprintf('Filter operator "%s" requires an array value', $this->operator->value));
        }
    }
}
