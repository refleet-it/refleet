<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\Register;

use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class RegisterCommand implements CommandInterface
{
    public function __construct(
        public string $email,
        #[\SensitiveParameter]
        public string $plainPassword,
        public RoleEnum $role,
        public bool $termsAccepted,
        public bool $marketingConsent = false,
        public bool $skipEmailVerification = false,
    ) {
    }
}
