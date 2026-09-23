<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\CreateOrganization;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class CreateOrganizationCommand implements CommandInterface
{
    public function __construct(
        public string $accountId,
        public string $email,
        public string $name,
    ) {
    }
}
