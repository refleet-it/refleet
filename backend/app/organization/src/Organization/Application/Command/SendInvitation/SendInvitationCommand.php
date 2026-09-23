<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\SendInvitation;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class SendInvitationCommand implements CommandInterface
{
    public function __construct(
        public string $requestingAccountId,
        public string $email,
    ) {
    }
}
