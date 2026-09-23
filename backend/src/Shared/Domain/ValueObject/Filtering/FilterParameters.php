<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject\Filtering;

final readonly class FilterParameters
{
    /**
     * @param array<FilterCriteria> $criteria
     */
    public function __construct(
        public array $criteria = [],
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string>        $allowedFields
     */
    public static function fromRequest(array $filters, array $allowedFields = []): self
    {
        $criteria = [];

        foreach ($filters as $field => $filterData) {
            if (\is_string($filterData)) {
                // Simple format: field=value (defaults to equals)
                $criteria[] = FilterCriteria::fromRequest(
                    field: $field,
                    operator: 'eq',
                    value: $filterData,
                    allowedFields: $allowedFields,
                );
            } elseif (\is_array($filterData)) {
                // Complex format: field[operator]=value
                foreach ($filterData as $operator => $value) {
                    $criteria[] = FilterCriteria::fromRequest(
                        field: $field,
                        operator: $operator,
                        value: $value,
                        allowedFields: $allowedFields,
                    );
                }
            }
        }

        return new self($criteria);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function add(FilterCriteria $criteria): self
    {
        return new self([...$this->criteria, $criteria]);
    }

    /**
     * @return array<FilterCriteria>
     */
    public function getCriteria(): array
    {
        return $this->criteria;
    }

    public function isEmpty(): bool
    {
        return [] === $this->criteria;
    }

    public function count(): int
    {
        return \count($this->criteria);
    }

    /**
     * @return array<FilterCriteria>
     */
    public function getByField(string $field): array
    {
        return \array_filter(
            $this->criteria,
            static fn (FilterCriteria $criteria) => $criteria->field === $field
        );
    }
}
