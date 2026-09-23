<?php

declare(strict_types=1);

namespace App\Shared\Application\Query;

use App\Shared\Domain\ValueObject\CursorListParameters;
use App\Shared\Domain\ValueObject\CursorListResponse;
use App\Shared\Domain\ValueObject\Filtering\FilterCriteria;
use App\Shared\Domain\ValueObject\Filtering\FilterOperator;

/**
 * @template T
 *
 * @implements CursorListQueryInterface<T>
 *
 * @codeCoverageIgnore
 */
abstract readonly class AbstractCursorListQuery implements CursorListQueryInterface
{
    #[\Override]
    public function getList(CursorListParameters $parameters): CursorListResponse
    {
        // Get items with pagination applied but before trimming to limit
        $items = $this->fetchItems($parameters);
        $items = $this->applyFiltering($items, $parameters);
        $items = $this->applySorting($items, $parameters);
        $items = $this->applyCursorPagination($items, $parameters);

        // Check if there are more pages before trimming
        $hasNextPage = $this->hasNextPage($items, $parameters);

        // Remove the extra item we fetched to check if there are more pages
        $limit = $parameters->getPagination()->getLimit();
        if (\count($items) > $limit) {
            $items = \array_slice($items, 0, $limit);
        }

        // Get next cursor from the trimmed items
        $nextCursor = $parameters->getPagination()->getNextCursor($items);

        return CursorListResponse::create(
            items: $items,
            pagination: $parameters->getPagination(),
            nextCursor: $nextCursor,
            hasNextPage: $hasNextPage,
        );
    }

    #[\Override]
    public function getAll(CursorListParameters $parameters): array
    {
        $items = $this->fetchItems($parameters);
        $items = $this->applyFiltering($items, $parameters);
        $items = $this->applySorting($items, $parameters);
        $items = $this->applyCursorPagination($items, $parameters);

        // Remove the extra item we fetched to check if there are more pages
        $limit = $parameters->getPagination()->getLimit();
        if (\count($items) > $limit) {
            $items = \array_slice($items, 0, $limit);
        }

        return $items;
    }

    /**
     * @return T[]
     */
    abstract protected function fetchItems(CursorListParameters $parameters): array;

    /**
     * @param T[] $items
     *
     * @return T[]
     */
    protected function applyFiltering(array $items, ?CursorListParameters $parameters = null): array
    {
        if (null === $parameters || !$parameters->hasFiltering()) {
            return $items;
        }

        $filtering = $parameters->getFiltering();
        if ($filtering->isEmpty()) {
            return $items;
        }

        return \array_filter($items, fn (mixed $item) => \array_all($filtering->getCriteria(), fn ($criteria) => $this->matchesCriteria($item, $criteria)));
    }

    /**
     * @param T $item
     */
    protected function matchesCriteria(mixed $item, FilterCriteria $criteria): bool
    {
        if (!\is_object($item)) {
            return false;
        }

        $fieldValue = $this->resolveFieldValue($item, $criteria->getField());
        if (false === $fieldValue) {
            return false;
        }

        return $this->applyOperator($fieldValue, $criteria->getOperator(), $criteria->getValue());
    }

    /**
     * @param T[] $items
     *
     * @return T[]
     */
    protected function applySorting(array $items, ?CursorListParameters $parameters = null): array
    {
        if (null === $parameters || !$parameters->hasSorting()) {
            return $items;
        }

        $sorting = $parameters->getSorting();
        if (null === $sorting) {
            return $items;
        }

        \usort($items, function (mixed $a, mixed $b) use ($sorting) {
            $field = $sorting->getField();
            $direction = $sorting->getDirection();

            $aValue = $this->getFieldValue($a, $field);
            $bValue = $this->getFieldValue($b, $field);

            if ($aValue === $bValue) {
                return 0;
            }

            $result = $this->compareValues($aValue, $bValue);

            return $direction->isAscending() ? $result : -$result;
        });

        return $items;
    }

    /**
     * @param T[] $items
     *
     * @return T[]
     */
    protected function applyCursorPagination(array $items, CursorListParameters $parameters): array
    {
        $pagination = $parameters->getPagination();
        $limit = $pagination->getLimit();

        // Fetch one extra item to check if there are more pages
        $items = \array_slice($items, 0, $limit + 1);

        return $items;
    }

    /**
     * @param T[] $items
     */
    protected function hasNextPage(array $items, CursorListParameters $parameters): bool
    {
        $limit = $parameters->getPagination()->getLimit();

        return \count($items) > $limit;
    }

    private function resolveFieldValue(object $item, string $field): mixed
    {
        try {
            $reflection = new \ReflectionClass($item);

            if ($reflection->hasProperty($field)) {
                return $reflection->getProperty($field)->getValue($item);
            }

            foreach ([$field, 'get'.\ucfirst($field), 'is'.\ucfirst($field), $field.'()'] as $methodName) {
                if ($reflection->hasMethod($methodName)) {
                    return $reflection->getMethod($methodName)->invoke($item);
                }
            }
        } catch (\ReflectionException) {
            return false;
        }

        return false;
    }

    private function applyOperator(mixed $fieldValue, FilterOperator $operator, mixed $value): bool
    {
        return match ($operator) {
            FilterOperator::EQUALS => 0 === $this->compareValues($fieldValue, $value),
            FilterOperator::NOT_EQUALS => 0 !== $this->compareValues($fieldValue, $value),
            FilterOperator::GREATER_THAN => $this->compareValues($fieldValue, $value) > 0,
            FilterOperator::GREATER_THAN_OR_EQUAL => $this->compareValues($fieldValue, $value) >= 0,
            FilterOperator::LESS_THAN => $this->compareValues($fieldValue, $value) < 0,
            FilterOperator::LESS_THAN_OR_EQUAL => $this->compareValues($fieldValue, $value) <= 0,
            FilterOperator::CONTAINS => $this->containsValue($fieldValue, $value),
            FilterOperator::NOT_CONTAINS => !$this->containsValue($fieldValue, $value),
            FilterOperator::STARTS_WITH => $this->startsWithValue($fieldValue, $value),
            FilterOperator::ENDS_WITH => $this->endsWithValue($fieldValue, $value),
            FilterOperator::IN => $this->inValue($fieldValue, $value),
            FilterOperator::NOT_IN => !$this->inValue($fieldValue, $value),
            FilterOperator::IS_NULL => null === $fieldValue,
            FilterOperator::IS_NOT_NULL => null !== $fieldValue,
        };
    }

    private function compareValues(mixed $a, mixed $b): int
    {
        if (\is_string($a) && \is_string($b)) {
            return \strcmp($a, $b);
        }

        if ($this->areComparableScalars($a, $b)) {
            return $a <=> $b;
        }

        if ($a instanceof \DateTimeInterface && $b instanceof \DateTimeInterface) {
            return $a <=> $b;
        }

        return \strcmp(
            \is_scalar($a) ? (string) $a : '',
            \is_scalar($b) ? (string) $b : '',
        );
    }

    private function areComparableScalars(mixed $a, mixed $b): bool
    {
        return (\is_numeric($a) && \is_numeric($b)) || (\is_bool($a) && \is_bool($b));
    }

    private function containsValue(mixed $a, mixed $b): bool
    {
        if (!\is_string($a) || !\is_string($b)) {
            return false;
        }

        return \str_contains($a, $b);
    }

    private function startsWithValue(mixed $a, mixed $b): bool
    {
        if (!\is_string($a) || !\is_string($b)) {
            return false;
        }

        return \str_starts_with($a, $b);
    }

    private function endsWithValue(mixed $a, mixed $b): bool
    {
        if (!\is_string($a) || !\is_string($b)) {
            return false;
        }

        return \str_ends_with($a, $b);
    }

    private function inValue(mixed $a, mixed $b): bool
    {
        if (!\is_array($b)) {
            return false;
        }

        return \in_array($a, $b, true);
    }

    /**
     * @param T $item
     */
    private function getFieldValue(mixed $item, string $field): mixed
    {
        if (!\is_object($item)) {
            return null;
        }

        $value = $this->resolveFieldValue($item, $field);

        return false === $value ? null : $value;
    }
}
