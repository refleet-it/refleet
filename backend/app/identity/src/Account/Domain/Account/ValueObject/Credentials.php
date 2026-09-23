<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\ValueObject;

final readonly class Credentials
{
    public function __construct(
        private string $email,
        private string $passwordHash,
    ) {
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function equals(self $other): bool
    {
        return $this->email === $other->email
            && $this->passwordHash === $other->passwordHash;
    }

    /**
     * Accepts hasher as parameter to avoid domain layer dependencies.
     */
    public function verifyPassword(string $plainPassword, callable $verifyCallback): bool
    {
        return $verifyCallback($plainPassword, $this->passwordHash);
    }
}
