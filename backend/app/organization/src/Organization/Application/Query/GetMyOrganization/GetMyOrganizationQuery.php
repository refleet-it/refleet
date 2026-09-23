<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Query\GetMyOrganization;

final readonly class GetMyOrganizationQuery
{
    public function __construct(
        public string $accountId,
    ) {
    }
}
