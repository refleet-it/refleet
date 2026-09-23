<?php

declare(strict_types=1);

namespace App\Shared\Domain\User;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;

class AuthenticatedUser implements UserInterface, SymfonyUserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param array<string> $roles
     */
    public function __construct(
        private readonly string $identifier,
        private readonly array $roles,
        private readonly ?string $password = null,
    ) {
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        if ('' === $this->identifier) {
            throw new \RuntimeException('User identifier cannot be empty');
        }

        return $this->identifier;
    }

    /**
     * @return array<string>
     */
    #[\Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    #[\Override]
    public function getPassword(): ?string
    {
        return $this->password;
    }

    #[\Override]
    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // No transient sensitive data stored on the user object
    }
}
