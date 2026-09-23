<?php

declare(strict_types=1);

namespace App\Tests\Integration\Organization\Organization\Infrastructure\Persistence;

use App\Organization\Organization\Domain\Employee\Enum\RoleEnum;
use App\Organization\Organization\Domain\Invitation\Model\Invitation;
use App\Organization\Organization\Domain\Invitation\ValueObject\InvitationId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Organization\Organization\Infrastructure\Persistence\DoctrineInvitationRepository;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[CoversClass(DoctrineInvitationRepository::class)]
final class DoctrineInvitationRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineInvitationRepository $repository;

    private EntityManagerInterface $entityManager;

    #[Test]
    public function saves_and_finds_an_invitation_by_token(): void
    {
        // Arrange
        $invitation = $this->createInvitation(OrganizationId::generate(), 'invitee@example.com', 'token-abc');

        // Act
        $this->repository->save($invitation);
        $this->entityManager->clear();
        $found = $this->repository->findByToken('token-abc');

        // Assert
        Assert::assertInstanceOf(Invitation::class, $found);
        Assert::assertSame('invitee@example.com', $found->email());
        Assert::assertTrue($found->isPending());
    }

    #[Test]
    public function finds_pending_invitations_for_an_organization_only(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $pending = $this->createInvitation($organizationId, 'pending@example.com', 'token-pending');
        $this->repository->save($pending);

        $accepted = $this->createInvitation($organizationId, 'accepted@example.com', 'token-accepted');
        $accepted->accept();

        $this->repository->save($accepted);

        $expired = $this->createInvitation($organizationId, 'expired@example.com', 'token-expired', new \DateTimeImmutable('-1 hour'));
        $this->repository->save($expired);

        $cancelled = $this->createInvitation($organizationId, 'cancelled@example.com', 'token-cancelled');
        $cancelled->cancel();

        $this->repository->save($cancelled);

        $otherOrg = $this->createInvitation(OrganizationId::generate(), 'other@example.com', 'token-other');
        $this->repository->save($otherOrg);

        $this->entityManager->clear();

        // Act
        $result = $this->repository->getPendingPaginatedList($organizationId, PaginationParameters::fromRequest(), null);

        // Assert
        Assert::assertCount(1, $result->getItems());
        Assert::assertSame('pending@example.com', $result->getItems()[0]->email());
    }

    #[Test]
    public function finds_pending_invitation_by_organization_and_email(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $invitation = $this->createInvitation($organizationId, 'Invitee@Example.com', 'token-xyz');
        $this->repository->save($invitation);
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findPendingByOrganizationIdAndEmail($organizationId, 'invitee@example.com');

        // Assert
        Assert::assertInstanceOf(Invitation::class, $found);
        Assert::assertSame('token-xyz', $found->token());
    }

    #[Test]
    public function finds_an_invitation_by_id(): void
    {
        // Arrange
        $invitation = $this->createInvitation(OrganizationId::generate(), 'invitee@example.com', 'token-by-id');
        $this->repository->save($invitation);
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findById($invitation->id());

        // Assert
        Assert::assertInstanceOf(Invitation::class, $found);
        Assert::assertSame('invitee@example.com', $found->email());
    }

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->repository = $container->get(DoctrineInvitationRepository::class);

        $managerRegistry = $container->get(ManagerRegistry::class);
        $entityManager = $managerRegistry->getManagerForClass(Invitation::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    private function createInvitation(
        OrganizationId $organizationId,
        string $email,
        string $token,
        ?\DateTimeImmutable $expiresAt = null,
    ): Invitation {
        return Invitation::create(
            id: InvitationId::generate(),
            organizationId: $organizationId,
            email: $email,
            role: RoleEnum::USER,
            invitedByAccountId: '11111111-2222-3333-4444-555555555555',
            token: $token,
            expiresAt: $expiresAt ?? new \DateTimeImmutable('+1 week'),
        );
    }
}
