<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\OrganizationContext;

/**
 * Resolves the organization a request acts on behalf of, so contexts other than
 * Organization can scope their work without depending on it directly.
 */
interface OrganizationContextProviderInterface
{
    /**
     * @throws \App\Shared\Domain\Exception\AccountHasNoOrganizationException when the account has no organization membership
     */
    public function requireForAccount(UserId $accountId): OrganizationContext;
}
