<?php

declare(strict_types=1);

namespace App\Shared\Domain\User;

final readonly class UserContext implements UserInterface
{
    /**
     * @param array<string> $roles
     */
    public function __construct(
        private UserId $userId,
        private array $roles,
    ) {
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        return $this->userId->asString();
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    /**
     * @return array<string>
     */
    #[\Override]
    public function getRoles(): array
    {
        return $this->roles;
    }
}
