<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\AcceptInvitation;

use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Invitation\Exception\InvitationAlreadyAcceptedException;
use App\Organization\Organization\Domain\Invitation\Exception\InvitationExpiredException;
use App\Organization\Organization\Domain\Invitation\Exception\InvitationNotFoundException;
use App\Organization\Organization\Domain\Invitation\Model\Invitation;
use App\Organization\Organization\Domain\Invitation\Repository\InvitationRepositoryInterface;
use App\Shared\Domain\Service\InvitedAccountRegistrarInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AcceptInvitationHandler
{
    public function __construct(
        private InvitationRepositoryInterface $invitationRepository,
        private EmployeeRepositoryInterface $employeeRepository,
        private InvitedAccountRegistrarInterface $accountRegistrar,
    ) {
    }

    /**
     * @throws InvitationNotFoundException
     * @throws InvitationAlreadyAcceptedException
     * @throws InvitationExpiredException
     */
    public function __invoke(AcceptInvitationCommand $command): AcceptedInvitation
    {
        $invitation = $this->invitationRepository->findByToken($command->token);
        $this->assertUsable($invitation);
        \assert($invitation instanceof Invitation);

        $accountId = $this->accountRegistrar->registerFromInvitation($invitation->email(), $command->password);

        // The account-created announcement that mirrors people into this context travels
        // over a queue, so the mirror is created here rather than waited for. The queued
        // handler skips accounts it already finds.
        $employee = $this->employeeRepository->findByAccountId(AccountId::fromString($accountId))
            ?? Employee::mirror(AccountId::fromString($accountId), $invitation->email());

        $employee->joinOrganization($invitation->organizationId(), $invitation->role());

        $this->employeeRepository->save($employee);

        $invitation->accept();
        $this->invitationRepository->save($invitation);

        return new AcceptedInvitation(email: $invitation->email());
    }

    private function assertUsable(?Invitation $invitation): void
    {
        if (null === $invitation) {
            throw new InvitationNotFoundException();
        }

        if ($invitation->isAccepted()) {
            throw new InvitationAlreadyAcceptedException();
        }

        if ($invitation->isExpired()) {
            throw new InvitationExpiredException();
        }
    }
}
