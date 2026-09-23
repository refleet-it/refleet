<?php

declare(strict_types=1);

namespace App\Tests\Integration\Runner\Runner\Infrastructure\Persistence;

use App\Runner\Runner\Domain\Runner\Enum\RunnerStatusEnum;
use App\Runner\Runner\Domain\Runner\Model\Runner;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use App\Runner\Runner\Infrastructure\Persistence\DoctrineRunnerRepository;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[CoversClass(DoctrineRunnerRepository::class)]
final class DoctrineRunnerRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineRunnerRepository $repository;

    private EntityManagerInterface $entityManager;

    #[Test]
    public function paginates_runners_across_pages(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        foreach (['runner-a', 'runner-b', 'runner-c'] as $name) {
            $this->repository->save(Runner::register(
                id: RunnerId::generate(),
                organizationId: $organizationId,
                name: $name,
                status: RunnerStatusEnum::OFFLINE,
            ));
        }

        $this->entityManager->clear();

        // Act
        $firstPage = $this->repository->getPaginatedList($organizationId, PaginationParameters::fromRequest(page: 1, limit: 2), null);

        // Assert
        Assert::assertCount(2, $firstPage->getItems());
        Assert::assertSame(3, $firstPage->getTotalItems());
        Assert::assertTrue($firstPage->hasNextPage());

        // Act
        $secondPage = $this->repository->getPaginatedList($organizationId, PaginationParameters::fromRequest(page: 2, limit: 2), null);

        // Assert
        Assert::assertCount(1, $secondPage->getItems());
        Assert::assertFalse($secondPage->hasNextPage());
    }

    #[Test]
    public function finds_a_runner_by_id(): void
    {
        // Arrange
        $id = RunnerId::generate();
        $this->repository->save(Runner::register(
            id: $id,
            organizationId: OrganizationId::generate(),
            name: 'runner-a',
            status: RunnerStatusEnum::OFFLINE,
        ));
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findById($id);

        // Assert
        Assert::assertInstanceOf(Runner::class, $found);
        Assert::assertTrue($found->id()->equals($id));
    }

    #[Test]
    public function returns_null_when_no_runner_matches_the_id(): void
    {
        // Act
        $found = $this->repository->findById(RunnerId::generate());

        // Assert
        Assert::assertNull($found);
    }

    #[Test]
    public function does_not_include_runners_from_another_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $this->repository->save(Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-a',
            status: RunnerStatusEnum::OFFLINE,
        ));
        $this->repository->save(Runner::register(
            id: RunnerId::generate(),
            organizationId: OrganizationId::generate(),
            name: 'runner-b',
            status: RunnerStatusEnum::OFFLINE,
        ));
        $this->entityManager->clear();

        // Act
        $result = $this->repository->getPaginatedList($organizationId, PaginationParameters::fromRequest(), null);

        // Assert
        Assert::assertCount(1, $result->getItems());
        Assert::assertSame('runner-a', $result->getItems()[0]->name());
    }

    #[Test]
    public function finds_a_runner_by_organization_id_and_name(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $this->repository->save(Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-a',
            status: RunnerStatusEnum::OFFLINE,
        ));
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findByOrganizationIdAndName($organizationId, 'runner-a');
        $notFound = $this->repository->findByOrganizationIdAndName($organizationId, 'runner-does-not-exist');

        // Assert
        Assert::assertInstanceOf(Runner::class, $found);
        Assert::assertNull($notFound);
    }

    #[Test]
    public function finds_runner_by_id_when_it_belongs_to_the_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $id = RunnerId::generate();
        $this->repository->save(Runner::register(
            id: $id,
            organizationId: $organizationId,
            name: 'runner-fleet-01',
            status: RunnerStatusEnum::OFFLINE,
        ));
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findByIdForOrganization($id, $organizationId);

        // Assert
        Assert::assertInstanceOf(Runner::class, $found);
        Assert::assertTrue($found->id()->equals($id));
    }

    #[Test]
    public function returns_null_when_runner_belongs_to_a_different_organization(): void
    {
        // Arrange
        $id = RunnerId::generate();
        $this->repository->save(Runner::register(
            id: $id,
            organizationId: OrganizationId::generate(),
            name: 'someone-elses-runner',
            status: RunnerStatusEnum::OFFLINE,
        ));
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findByIdForOrganization($id, OrganizationId::generate());

        // Assert
        Assert::assertNull($found);
    }

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->repository = $container->get(DoctrineRunnerRepository::class);

        $managerRegistry = $container->get(ManagerRegistry::class);
        $entityManager = $managerRegistry->getManagerForClass(Runner::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }
}
