<?php

declare(strict_types=1);

namespace App\Tests\Integration\Project\Project\Infrastructure\Persistence;

use App\Project\Project\Domain\Project\Model\Project;
use App\Project\Project\Domain\Project\ValueObject\GitLabProjectId;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Project\Project\Domain\Project\ValueObject\ProjectId;
use App\Project\Project\Infrastructure\Persistence\DoctrineProjectRepository;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[CoversClass(DoctrineProjectRepository::class)]
final class DoctrineProjectRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineProjectRepository $repository;

    private EntityManagerInterface $entityManager;

    #[Test]
    public function saves_and_finds_a_project_by_id(): void
    {
        // Arrange
        $id = ProjectId::generate();
        $project = Project::register(
            id: $id,
            organizationId: OrganizationId::generate(),
            externalId: GitLabProjectId::fromString('48210942'),
            name: 'Payments Service',
            path: 'team/payments-service',
            webUrl: 'https://gitlab.com/team/payments-service',
            defaultBranch: 'main',
            description: 'Handles payment processing',
        );

        // Act
        $this->repository->save($project);
        $this->entityManager->clear();
        $found = $this->repository->findById($id);

        // Assert
        Assert::assertInstanceOf(Project::class, $found);
        Assert::assertSame('Payments Service', $found->name());
    }

    #[Test]
    public function finds_a_project_by_organization_and_external_id(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $externalId = GitLabProjectId::fromString('48210942');
        $project = Project::register(
            id: ProjectId::generate(),
            organizationId: $organizationId,
            externalId: $externalId,
            name: 'Payments Service',
            path: 'team/payments-service',
            webUrl: null,
            defaultBranch: null,
            description: null,
        );
        $this->repository->save($project);
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findByExternalId($organizationId, $externalId);

        // Assert
        Assert::assertInstanceOf(Project::class, $found);
        Assert::assertTrue($found->id()->equals($project->id()));
    }

    #[Test]
    public function lists_active_projects_for_an_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $this->repository->save(Project::register(
            id: ProjectId::generate(),
            organizationId: $organizationId,
            externalId: GitLabProjectId::fromString('1'),
            name: 'Service A',
            path: 'team/service-a',
            webUrl: null,
            defaultBranch: null,
            description: null,
        ));
        $archived = Project::register(
            id: ProjectId::generate(),
            organizationId: $organizationId,
            externalId: GitLabProjectId::fromString('2'),
            name: 'Service B',
            path: 'team/service-b',
            webUrl: null,
            defaultBranch: null,
            description: null,
        );
        $archived->archive();

        $this->repository->save($archived);
        $this->repository->save(Project::register(
            id: ProjectId::generate(),
            organizationId: OrganizationId::generate(),
            externalId: GitLabProjectId::fromString('3'),
            name: "Someone Else's Service",
            path: 'other-team/service-c',
            webUrl: null,
            defaultBranch: null,
            description: null,
        ));
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findActiveByOrganizationId($organizationId);

        // Assert
        Assert::assertCount(1, $found);
        Assert::assertSame('Service A', $found[0]->name());
    }

    #[Test]
    public function paginates_active_projects_across_pages(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        foreach (['Service A', 'Service B', 'Service C'] as $index => $name) {
            $this->repository->save(Project::register(
                id: ProjectId::generate(),
                organizationId: $organizationId,
                externalId: GitLabProjectId::fromString((string) ($index + 1)),
                name: $name,
                path: 'team/'.\strtolower(\str_replace(' ', '-', $name)),
                webUrl: null,
                defaultBranch: null,
                description: null,
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
    public function returns_null_when_project_does_not_exist(): void
    {
        // Act
        $found = $this->repository->findById(ProjectId::generate());

        // Assert
        Assert::assertNull($found);
    }

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->repository = $container->get(DoctrineProjectRepository::class);

        $managerRegistry = $container->get(ManagerRegistry::class);
        $entityManager = $managerRegistry->getManagerForClass(Project::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }
}
