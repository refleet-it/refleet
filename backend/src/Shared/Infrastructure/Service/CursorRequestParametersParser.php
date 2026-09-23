<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Service;

use App\Shared\Domain\ValueObject\CursorListParameters;
use Symfony\Component\HttpFoundation\Request;

final readonly class CursorRequestParametersParser
{
    private const string PARAM_CURSOR = 'cursor';

    private const string PARAM_LIMIT = 'limit';

    private const string PARAM_SORT_BY = 'sortBy';

    private const string PARAM_SORT_DIRECTION = 'sortDirection';

    private const string PARAM_FILTERS = 'filters';

    /**
     * @param array<string> $allowedSortFields
     * @param array<string> $allowedFilterFields
     */
    public function __construct(
        private array $allowedSortFields = [],
        private array $allowedFilterFields = [],
    ) {
    }

    /**
     * @param array<string> $allowedSortFields
     * @param array<string> $allowedFilterFields
     */
    public static function create(
        array $allowedSortFields = [],
        array $allowedFilterFields = [],
    ): self {
        return new self($allowedSortFields, $allowedFilterFields);
    }

    public function parseFromRequest(Request $request): CursorListParameters
    {
        $queryParams = $request->query->all();

        return CursorListParameters::fromRequest(
            cursor: $this->parseString($queryParams[self::PARAM_CURSOR] ?? null),
            limit: $this->parseInt($queryParams[self::PARAM_LIMIT] ?? null),
            sortBy: $this->parseString($queryParams[self::PARAM_SORT_BY] ?? null),
            sortDirection: $this->parseString($queryParams[self::PARAM_SORT_DIRECTION] ?? null),
            filters: $this->parseFilters($queryParams),
            allowedSortFields: $this->allowedSortFields,
            allowedFilterFields: $this->allowedFilterFields,
        );
    }

    public function parseSimpleFromRequest(Request $request): CursorListParameters
    {
        $queryParams = $request->query->all();

        return CursorListParameters::simple(
            cursor: $this->parseString($queryParams[self::PARAM_CURSOR] ?? null),
            limit: $this->parseInt($queryParams[self::PARAM_LIMIT] ?? null),
            sortBy: $this->parseString($queryParams[self::PARAM_SORT_BY] ?? null),
            sortDirection: $this->parseString($queryParams[self::PARAM_SORT_DIRECTION] ?? null),
        );
    }

    /**
     * @param array<string> $allowedSortFields
     */
    public function withAllowedSortFields(array $allowedSortFields): self
    {
        return new self($allowedSortFields, $this->allowedFilterFields);
    }

    /**
     * @param array<string> $allowedFilterFields
     */
    public function withAllowedFilterFields(array $allowedFilterFields): self
    {
        return new self($this->allowedSortFields, $allowedFilterFields);
    }

    private function parseString(mixed $value): ?string
    {
        if (null === $value || '' === $value || !\is_string($value)) {
            return null;
        }

        return $value;
    }

    private function parseInt(mixed $value): ?int
    {
        if (null === $value || '' === $value || !\is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }

    /**
     * @param array<string, mixed> $queryParams
     *
     * @return array<string, mixed>
     */
    private function parseFilters(array $queryParams): array
    {
        $filters = [];

        // Parse simple filters: ?name=value
        foreach ($queryParams as $key => $value) {
            if (\in_array($key, [self::PARAM_CURSOR, self::PARAM_LIMIT, self::PARAM_SORT_BY, self::PARAM_SORT_DIRECTION], true)) {
                continue;
            }

            if (\is_string($value) && '' !== $value) {
                $filters[$key] = $value;
            }
        }

        // Parse complex filters: ?filters[name][contains]=value
        if (isset($queryParams[self::PARAM_FILTERS]) && \is_array($queryParams[self::PARAM_FILTERS])) {
            foreach ($queryParams[self::PARAM_FILTERS] as $field => $operators) {
                if (\is_array($operators)) {
                    $filters[$field] = $operators;
                }
            }
        }

        return $filters;
    }
}
