<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\ValueObject;

final readonly class Email implements \Stringable
{
    public function __construct(
        private string $value,
    ) {
        if (false === \filter_var($this->value, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address');
        }
    }

    public static function fromString(string $email): self
    {
        return new self($email);
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
