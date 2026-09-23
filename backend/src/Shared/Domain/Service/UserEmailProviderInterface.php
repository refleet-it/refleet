<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

use App\Shared\Domain\User\UserId;

/**
 * Interface for retrieving user email addresses.
 * This allows other contexts to get user emails without direct dependency on Identity context.
 */
interface UserEmailProviderInterface
{
    public function getEmailByUserId(UserId $userId): ?string;
}
