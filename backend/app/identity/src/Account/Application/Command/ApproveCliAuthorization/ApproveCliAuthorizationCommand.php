<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\ApproveCliAuthorization;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ApproveCliAuthorizationCommand implements CommandInterface
{
    public function __construct(
        public string $accountId,
        public string $userCode,
    ) {
    }
}
