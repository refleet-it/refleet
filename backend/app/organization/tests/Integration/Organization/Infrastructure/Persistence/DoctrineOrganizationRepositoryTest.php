<?php

declare(strict_types=1);

namespace App\Tests\Integration\Organization\Organization\Infrastructure\Persistence;

use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\Model\Organization;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Organization\Organization\Infrastructure\Persistence\DoctrineOrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[CoversClass(DoctrineOrganizationRepository::class)]
final class DoctrineOrganizationRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineOrganizationRepository $repository;

    private EntityManagerInterface $entityManager;

    #[Test]
    public function saves_and_finds_an_organization_by_id(): void
    {
        // Arrange
        $id = OrganizationId::generate();
        $organization = Organization::create($id, 'Acme Inc.', AccountId::generate());

        // Act
        $this->repository->save($organization);
        $this->entityManager->clear();
        $found = $this->repository->findById($id);

        // Assert
        Assert::assertInstanceOf(Organization::class, $found);
        Assert::assertSame('Acme Inc.', $found->name());
    }

    #[Test]
    public function returns_null_when_organization_does_not_exist(): void
    {
        // Act
        $found = $this->repository->findById(OrganizationId::generate());

        // Assert
        Assert::assertNull($found);
    }

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->repository = $container->get(DoctrineOrganizationRepository::class);

        $managerRegistry = $container->get(ManagerRegistry::class);
        $entityManager = $managerRegistry->getManagerForClass(Organization::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }
}
