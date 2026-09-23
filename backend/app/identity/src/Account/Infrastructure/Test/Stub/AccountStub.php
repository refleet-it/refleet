<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Test\Stub;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final readonly class AccountStub implements PasswordAuthenticatedUserInterface
{
    public function __construct(
        private string $password = '',
    ) {
    }

    #[\Override]
    public function getPassword(): string
    {
        return $this->password;
    }
}
