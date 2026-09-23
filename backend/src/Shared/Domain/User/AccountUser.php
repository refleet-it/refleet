<?php

declare(strict_types=1);

namespace App\Shared\Domain\User;

final class AccountUser extends AuthenticatedUser
{
    public function __construct(
        string $email,
        array $roles,
        ?string $passwordHash,
        private readonly UserId $userId,
        private readonly ?string $impersonatorId = null,
        /** Set only when the request authenticated with an API key, null for JWT sessions. */
        private readonly ?string $apiKeyId = null,
    ) {
        parent::__construct($email, $roles, $passwordHash);
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function hasRole(string $role): bool
    {
        return \in_array($role, $this->getRoles(), true);
    }

    public function getImpersonatorId(): ?string
    {
        return $this->impersonatorId;
    }

    public function getApiKeyId(): ?string
    {
        return $this->apiKeyId;
    }
}
