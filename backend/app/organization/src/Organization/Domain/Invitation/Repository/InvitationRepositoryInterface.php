<?php

declare(strict_types=1);

namespace App\Organization\Organization\Domain\Invitation\Repository;

use App\Organization\Organization\Domain\Invitation\Model\Invitation;
use App\Organization\Organization\Domain\Invitation\ValueObject\InvitationId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;

interface InvitationRepositoryInterface
{
    public function save(Invitation $invitation): void;

    public function findById(InvitationId $id): ?Invitation;

    public function findByToken(string $token): ?Invitation;

    /**
     * @return ListResponse<Invitation>
     */
    public function getPendingPaginatedList(OrganizationId $organizationId, PaginationParameters $pagination, ?SortParameters $sorting): ListResponse;

    public function findPendingByOrganizationIdAndEmail(OrganizationId $organizationId, string $email): ?Invitation;
}
