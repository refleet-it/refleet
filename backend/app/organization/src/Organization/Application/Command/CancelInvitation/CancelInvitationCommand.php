<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\CancelInvitation;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class CancelInvitationCommand implements CommandInterface
{
    public function __construct(
        public string $requestingAccountId,
        public string $invitationId,
    ) {
    }
}
