<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\ValueObject;

final readonly class HashedPassword implements \Stringable
{
    public function __construct(
        private string $value,
    ) {
        if ('' === $this->value) {
            throw new \InvalidArgumentException('Hashed password cannot be empty');
        }
    }

    public static function fromString(string $hashedPassword): self
    {
        return new self($hashedPassword);
    }

    public function asString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
