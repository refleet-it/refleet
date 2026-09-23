<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Service;

use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;

interface TokenGenerator
{
    public function generate(Account $account, ?AccountId $impersonatorId = null): string;
}
