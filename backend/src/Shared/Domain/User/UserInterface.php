<?php

declare(strict_types=1);

namespace App\Shared\Domain\User;

interface UserInterface
{
    public function getUserIdentifier(): string;

    /**
     * @return array<string>
     */
    public function getRoles(): array;
}
