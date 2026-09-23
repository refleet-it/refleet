<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Security;

use App\Identity\Account\Infrastructure\Service\PasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    #[\Override]
    public function hash(string $plain): string
    {
        $user = new class implements UserInterface, PasswordAuthenticatedUserInterface {
            #[\Override]
            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            #[\Deprecated]
            #[\Override]
            public function eraseCredentials(): void
            {
            }

            #[\Override]
            public function getUserIdentifier(): string
            {
                return 'temp';
            }

            #[\Override]
            public function getPassword(): string
            {
                return '';
            }
        };

        return $this->hasher->hashPassword($user, $plain);
    }

    #[\Override]
    public function verify(string $plain, string $hash): bool
    {
        $user = new class($hash) implements UserInterface, PasswordAuthenticatedUserInterface {
            public function __construct(private readonly string $passwordHash)
            {
            }

            #[\Override]
            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            #[\Deprecated]
            #[\Override]
            public function eraseCredentials(): void
            {
            }

            #[\Override]
            public function getUserIdentifier(): string
            {
                return 'temp';
            }

            #[\Override]
            public function getPassword(): string
            {
                return $this->passwordHash;
            }
        };

        return $this->hasher->isPasswordValid($user, $plain);
    }
}
