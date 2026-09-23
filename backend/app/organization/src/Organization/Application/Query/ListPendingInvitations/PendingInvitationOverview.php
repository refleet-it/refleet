<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Query\ListPendingInvitations;

final readonly class PendingInvitationOverview
{
    public function __construct(
        public string $id,
        public string $email,
        public string $createdAt,
        public string $expiresAt,
    ) {
    }
}
