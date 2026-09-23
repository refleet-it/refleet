<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\AcceptInvitation;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class AcceptInvitationCommand implements CommandInterface
{
    public function __construct(
        public string $token,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }
}
