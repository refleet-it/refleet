<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Query\ListPendingInvitations;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\NotOrganizationOwnerException;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Invitation\Model\Invitation;
use App\Organization\Organization\Domain\Invitation\Repository\InvitationRepositoryInterface;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\ValueObject\ListParameters;
use App\Shared\Domain\ValueObject\ListResponse;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPendingInvitationsHandler
{
    private const array ALLOWED_SORT_FIELDS = ['email', 'expiresAt', 'createdAt'];

    public function __construct(
        private EmployeeRepositoryInterface $employeeRepository,
        private InvitationRepositoryInterface $invitationRepository,
    ) {
    }

    /**
     * @return ListResponse<PendingInvitationOverview>
     *
     * @throws NotOrganizationOwnerException
     */
    public function __invoke(ListPendingInvitationsQuery $query): ListResponse
    {
        $organizationId = $this->assertRequesterIsOwner($query->requestingAccountId);

        $parameters = ListParameters::fromRequest(
            page: $query->page,
            limit: $query->limit,
            sortBy: $query->sortBy,
            sortDirection: $query->sortDirection,
            allowedSortFields: self::ALLOWED_SORT_FIELDS,
        );

        $result = $this->invitationRepository->getPendingPaginatedList($organizationId, $parameters->getPagination(), $parameters->getSorting());

        /* @var ListResponse<PendingInvitationOverview> */
        return ListResponse::create(
            items: \array_map($this->toOverview(...), $result->getItems()),
            totalItems: $result->getTotalItems(),
            pagination: $result->getPagination(),
        );
    }

    /**
     * @throws NotOrganizationOwnerException
     */
    private function assertRequesterIsOwner(string $requestingAccountId): OrganizationId
    {
        $requester = $this->employeeRepository->findByAccountId(AccountId::fromString($requestingAccountId));
        $organizationId = $requester?->organizationId();

        if (null === $requester || null === $organizationId || RoleEnum::OWNER !== $requester->role()) {
            throw new NotOrganizationOwnerException();
        }

        return $organizationId;
    }

    private function toOverview(Invitation $invitation): PendingInvitationOverview
    {
        return new PendingInvitationOverview(
            id: $invitation->id()->asString(),
            email: $invitation->email(),
            createdAt: $invitation->createdAt()->format('c'),
            expiresAt: $invitation->expiresAt()->format('c'),
        );
    }
}
