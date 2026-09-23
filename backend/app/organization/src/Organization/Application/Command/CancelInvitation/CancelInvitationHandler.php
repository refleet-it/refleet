<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\CancelInvitation;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\NotOrganizationOwnerException;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Invitation\Exception\InvitationNotFoundException;
use App\Organization\Organization\Domain\Invitation\Exception\InvitationNotPendingException;
use App\Organization\Organization\Domain\Invitation\Repository\InvitationRepositoryInterface;
use App\Organization\Organization\Domain\Invitation\ValueObject\InvitationId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CancelInvitationHandler
{
    public function __construct(
        private EmployeeRepositoryInterface $employeeRepository,
        private InvitationRepositoryInterface $invitationRepository,
    ) {
    }

    /**
     * @throws NotOrganizationOwnerException
     * @throws InvitationNotFoundException
     * @throws InvitationNotPendingException
     */
    public function __invoke(CancelInvitationCommand $command): void
    {
        $organizationId = $this->assertRequesterIsOwner($command->requestingAccountId);

        $invitation = $this->invitationRepository->findById(InvitationId::fromString($command->invitationId));
        if (null === $invitation || !$invitation->organizationId()->equals($organizationId)) {
            throw new InvitationNotFoundException();
        }

        if (!$invitation->isPending()) {
            throw new InvitationNotPendingException();
        }

        $invitation->cancel();
        $this->invitationRepository->save($invitation);
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
}
