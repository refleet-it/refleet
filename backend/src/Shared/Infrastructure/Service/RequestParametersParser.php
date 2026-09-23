<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Service;

use App\Shared\Domain\ValueObject\ListParameters;
use Symfony\Component\HttpFoundation\Request;

final readonly class RequestParametersParser
{
    private const array RESERVED_PARAMS = ['page', 'limit', 'sortBy', 'sortDirection'];

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

    public function parseFromRequest(Request $request): ListParameters
    {
        $queryParams = $request->query->all();

        return ListParameters::fromRequest(
            page: $this->parseInt($queryParams['page'] ?? null),
            limit: $this->parseInt($queryParams['limit'] ?? null),
            sortBy: $this->parseString($queryParams['sortBy'] ?? null),
            sortDirection: $this->parseString($queryParams['sortDirection'] ?? null),
            filters: $this->parseFilters($queryParams),
            allowedSortFields: $this->allowedSortFields,
            allowedFilterFields: $this->allowedFilterFields,
        );
    }

    public function parseSimpleFromRequest(Request $request): ListParameters
    {
        $queryParams = $request->query->all();

        return ListParameters::simple(
            page: $this->parseInt($queryParams['page'] ?? null),
            limit: $this->parseInt($queryParams['limit'] ?? null),
            sortBy: $this->parseString($queryParams['sortBy'] ?? null),
            sortDirection: $this->parseString($queryParams['sortDirection'] ?? null),
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

    private function parseInt(mixed $value): ?int
    {
        if (null === $value || '' === $value || !\is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }

    private function parseString(mixed $value): ?string
    {
        if (null === $value || '' === $value || !\is_string($value)) {
            return null;
        }

        return $value;
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
            if (\in_array($key, self::RESERVED_PARAMS, true)) {
                continue;
            }

            if (\is_string($value) && '' !== $value) {
                $filters[$key] = $value;
            }
        }

        // Parse complex filters: ?filters[name][contains]=value
        if (isset($queryParams['filters']) && \is_array($queryParams['filters'])) {
            foreach ($queryParams['filters'] as $field => $operators) {
                if (\is_array($operators)) {
                    $filters[$field] = $operators;
                }
            }
        }

        return $filters;
    }
}
