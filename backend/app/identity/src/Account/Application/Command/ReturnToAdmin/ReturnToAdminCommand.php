<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\ReturnToAdmin;

use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ReturnToAdminCommand implements CommandInterface
{
    public function __construct(
        public AccountId $adminAccountId,
    ) {
    }
}
