<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use Ramsey\Uuid\Uuid;
use Webmozart\Assert\Assert;

readonly class Id implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {
        if (!Uuid::isValid($this->value)) {
            throw new \InvalidArgumentException('Invalid UUID: '.$this->value);
        }
    }

    public static function generate(): static
    {
        // @phpstan-ignore new.static (subclasses share this constructor's UUID validation, so calling it via `static` is safe here)
        return new static(Uuid::uuid7()->toString());
    }

    public static function fromString(string $value): static
    {
        Assert::uuid($value);

        // @phpstan-ignore new.static (subclasses share this constructor's UUID validation, so calling it via `static` is safe here)
        return new static($value);
    }

    public function asString(): string
    {
        return $this->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
