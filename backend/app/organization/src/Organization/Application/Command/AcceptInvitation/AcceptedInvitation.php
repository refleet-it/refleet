<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\AcceptInvitation;

final readonly class AcceptedInvitation
{
    public function __construct(
        public string $email,
    ) {
    }
}
