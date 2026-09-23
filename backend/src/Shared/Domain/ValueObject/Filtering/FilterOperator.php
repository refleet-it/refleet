<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject\Filtering;

enum FilterOperator: string
{
    public static function fromString(string $operator): self
    {
        return match (\strtolower($operator)) {
            'eq', 'equals', '=' => self::EQUALS,
            'ne', 'not_equals', '!=' => self::NOT_EQUALS,
            'gt', 'greater_than', '>' => self::GREATER_THAN,
            'gte', 'greater_than_or_equal', '>=' => self::GREATER_THAN_OR_EQUAL,
            'lt', 'less_than', '<' => self::LESS_THAN,
            'lte', 'less_than_or_equal', '<=' => self::LESS_THAN_OR_EQUAL,
            'contains', 'like' => self::CONTAINS,
            'not_contains', 'not_like' => self::NOT_CONTAINS,
            'starts_with', 'begins_with' => self::STARTS_WITH,
            'ends_with' => self::ENDS_WITH,
            'in' => self::IN,
            'not_in' => self::NOT_IN,
            'is_null', 'null' => self::IS_NULL,
            'is_not_null', 'not_null' => self::IS_NOT_NULL,
            default => throw new \InvalidArgumentException('Invalid filter operator: '.$operator),
        };
    }

    public function requiresValue(): bool
    {
        return !\in_array($this, [self::IS_NULL, self::IS_NOT_NULL], true);
    }

    public function supportsArrayValue(): bool
    {
        return \in_array($this, [self::IN, self::NOT_IN], true);
    }

    case EQUALS = 'eq';
    case NOT_EQUALS = 'ne';
    case GREATER_THAN = 'gt';
    case GREATER_THAN_OR_EQUAL = 'gte';
    case LESS_THAN = 'lt';
    case LESS_THAN_OR_EQUAL = 'lte';
    case CONTAINS = 'contains';
    case NOT_CONTAINS = 'not_contains';
    case STARTS_WITH = 'starts_with';
    case ENDS_WITH = 'ends_with';
    case IN = 'in';
    case NOT_IN = 'not_in';
    case IS_NULL = 'is_null';
    case IS_NOT_NULL = 'is_not_null';
}
