<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Query\ListPendingInvitations;

final readonly class ListPendingInvitationsQuery
{
    public function __construct(
        public string $requestingAccountId,
        public ?int $page = null,
        public ?int $limit = null,
        public ?string $sortBy = null,
        public ?string $sortDirection = null,
    ) {
    }
}
