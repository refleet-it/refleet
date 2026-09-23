<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class TenantId
{
    public function __construct(private string $value)
    {
        if ('' === $this->value) {
            throw new \InvalidArgumentException('TenantId cannot be empty');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function asString(): string
    {
        return $this->value;
    }
}
