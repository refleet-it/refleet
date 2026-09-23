<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\Command\SendInvitation;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Employee\Exception\AccountAlreadyBelongsToOrganizationException;
use App\Organization\Organization\Domain\Employee\Exception\NotOrganizationOwnerException;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Invitation\Exception\InvitationAlreadyPendingException;
use App\Organization\Organization\Domain\Invitation\Model\Invitation;
use App\Organization\Organization\Domain\Invitation\Repository\InvitationRepositoryInterface;
use App\Organization\Organization\Domain\Invitation\ValueObject\InvitationId;
use App\Organization\Organization\Domain\Organization\Exception\OrganizationNotFoundException;
use App\Organization\Organization\Domain\Organization\Model\Organization;
use App\Organization\Organization\Domain\Organization\Repository\OrganizationRepositoryInterface;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\Service\TemplateEmailSenderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendInvitationHandler
{
    private const int TOKEN_RANDOM_BYTES = 32;

    private const string EXPIRY = '+7 days';

    private const string ACCEPT_INVITATION_PATH = '/auth/accept-invitation?token=';

    private const string EMAIL_TEMPLATE = 'notifications/email/invitation.html.twig';

    public function __construct(
        private EmployeeRepositoryInterface $employeeRepository,
        private OrganizationRepositoryInterface $organizationRepository,
        private InvitationRepositoryInterface $invitationRepository,
        private TemplateEmailSenderInterface $emailSender,
        #[Autowire('%env(FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    /**
     * @throws NotOrganizationOwnerException
     * @throws AccountAlreadyBelongsToOrganizationException
     * @throws InvitationAlreadyPendingException
     * @throws OrganizationNotFoundException
     */
    public function __invoke(SendInvitationCommand $command): SentInvitation
    {
        $organizationId = $this->assertRequesterIsOwner($command->requestingAccountId);
        $this->assertEmailCanBeInvited($organizationId, $command->email);

        $organization = $this->organizationRepository->findById($organizationId);
        if (null === $organization) {
            throw new OrganizationNotFoundException();
        }

        $token = \bin2hex(\random_bytes(self::TOKEN_RANDOM_BYTES));
        $expiresAt = new \DateTimeImmutable(self::EXPIRY);

        $invitation = Invitation::create(
            id: InvitationId::generate(),
            organizationId: $organizationId,
            email: $command->email,
            role: RoleEnum::USER,
            invitedByAccountId: $command->requestingAccountId,
            token: $token,
            expiresAt: $expiresAt,
        );

        $this->invitationRepository->save($invitation);
        $this->sendInvitationEmail($invitation, $organization, $token);

        return new SentInvitation(
            id: $invitation->id()->asString(),
            email: $invitation->email(),
            expiresAt: $expiresAt->format('c'),
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

    /**
     * @throws AccountAlreadyBelongsToOrganizationException
     * @throws InvitationAlreadyPendingException
     */
    private function assertEmailCanBeInvited(OrganizationId $organizationId, string $email): void
    {
        $existingEmployee = $this->employeeRepository->findByEmail($email);
        if (null !== $existingEmployee && $existingEmployee->isInOrganization()) {
            throw new AccountAlreadyBelongsToOrganizationException();
        }

        if (null !== $this->invitationRepository->findPendingByOrganizationIdAndEmail($organizationId, $email)) {
            throw new InvitationAlreadyPendingException();
        }
    }

    private function sendInvitationEmail(Invitation $invitation, Organization $organization, string $token): void
    {
        $this->emailSender->sendWithTemplate(
            recipientEmail: $invitation->email(),
            subject: \sprintf('You have been invited to join %s on Refleet', $organization->name()),
            templatePath: self::EMAIL_TEMPLATE,
            templateData: [
                'organizationName' => $organization->name(),
                'acceptUrl' => $this->frontendUrl.self::ACCEPT_INVITATION_PATH.\urlencode($token),
            ],
        );
    }
}
