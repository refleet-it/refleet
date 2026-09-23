<?php

declare(strict_types=1);

namespace App\Shared\Application\Query;

use App\Shared\Domain\User\UserId;

interface AccountEmailProviderInterface
{
    public function getEmailByUserId(UserId $userId): ?string;
}
