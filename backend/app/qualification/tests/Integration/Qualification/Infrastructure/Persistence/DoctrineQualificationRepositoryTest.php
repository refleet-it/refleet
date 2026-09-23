<?php

declare(strict_types=1);

namespace App\Tests\Integration\Qualification\Qualification\Infrastructure\Persistence;

use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Infrastructure\Persistence\DoctrineQualificationRepository;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[CoversClass(DoctrineQualificationRepository::class)]
final class DoctrineQualificationRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineQualificationRepository $repository;

    private EntityManagerInterface $entityManager;

    #[Test]
    public function saves_and_finds_a_qualification_by_id(): void
    {
        // Arrange
        $id = QualificationId::generate();
        $qualification = Qualification::draft(
            id: $id,
            organizationId: OrganizationId::generate(),
            title: 'Bump acme/legacy-lib to v3',
            description: 'Removes the deprecated dependency across the fleet.',
            createdBy: AccountId::generate(),
            criteria: QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
        );

        // Act
        $this->repository->save($qualification);
        $this->entityManager->clear();
        $found = $this->repository->findById($id);

        // Assert
        Assert::assertInstanceOf(Qualification::class, $found);
        Assert::assertTrue($found->id()->equals($id));
    }

    #[Test]
    public function lists_qualifications_for_an_organization_newest_first(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $criteria = QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?');

        $first = Qualification::draft(
            id: QualificationId::generate(),
            organizationId: $organizationId,
            title: 'First qualification',
            description: null,
            createdBy: AccountId::generate(),
            criteria: $criteria,
        );
        $this->repository->save($first);

        \usleep(1_000);

        $second = Qualification::draft(
            id: QualificationId::generate(),
            organizationId: $organizationId,
            title: 'Second qualification',
            description: null,
            createdBy: AccountId::generate(),
            criteria: $criteria,
        );
        $this->repository->save($second);

        $this->repository->save(Qualification::draft(
            id: QualificationId::generate(),
            organizationId: OrganizationId::generate(),
            title: "Someone else's qualification",
            description: null,
            createdBy: AccountId::generate(),
            criteria: $criteria,
        ));

        $this->entityManager->clear();

        // Act
        $found = $this->repository->findByOrganizationId($organizationId);

        // Assert
        Assert::assertCount(2, $found);
        Assert::assertSame('Second qualification', $found[0]->title());
        Assert::assertSame('First qualification', $found[1]->title());
    }

    #[Test]
    public function paginates_qualifications_across_pages(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $criteria = QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?');
        foreach (['Qualification A', 'Qualification B', 'Qualification C'] as $title) {
            $this->repository->save(Qualification::draft(
                id: QualificationId::generate(),
                organizationId: $organizationId,
                title: $title,
                description: null,
                createdBy: AccountId::generate(),
                criteria: $criteria,
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
    public function keeps_archived_qualifications_off_the_live_page_and_lists_them_on_their_own(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $criteria = QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?');
        $live = Qualification::draft(id: QualificationId::generate(), organizationId: $organizationId, title: 'Live', description: null, createdBy: AccountId::generate(), criteria: $criteria);
        $archived = Qualification::draft(id: QualificationId::generate(), organizationId: $organizationId, title: 'Archived', description: null, createdBy: AccountId::generate(), criteria: $criteria);
        $archived->archive(new \DateTimeImmutable());

        $this->repository->save($live);
        $this->repository->save($archived);

        $this->entityManager->clear();

        // Act
        $livePage = $this->repository->getPaginatedList($organizationId, PaginationParameters::fromRequest(page: 1, limit: 10), null);
        $archivedPage = $this->repository->getPaginatedList($organizationId, PaginationParameters::fromRequest(page: 1, limit: 10), null, archived: true);

        // Assert
        Assert::assertCount(1, $livePage->getItems());
        Assert::assertSame('Live', $livePage->getItems()[0]->title());
        Assert::assertCount(1, $archivedPage->getItems());
        Assert::assertSame('Archived', $archivedPage->getItems()[0]->title());
        Assert::assertNotNull($archivedPage->getItems()[0]->archivedAt());
    }

    #[Test]
    public function returns_null_when_qualification_does_not_exist(): void
    {
        // Act
        $found = $this->repository->findById(QualificationId::generate());

        // Assert
        Assert::assertNull($found);
    }

    #[Test]
    public function finds_qualification_by_id_when_it_belongs_to_the_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $id = QualificationId::generate();
        $this->repository->save(Qualification::draft(
            id: $id,
            organizationId: $organizationId,
            title: 'Bump acme/legacy-lib to v3',
            description: null,
            createdBy: AccountId::generate(),
            criteria: QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
        ));
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findByIdForOrganization($id, $organizationId);

        // Assert
        Assert::assertInstanceOf(Qualification::class, $found);
        Assert::assertTrue($found->id()->equals($id));
    }

    #[Test]
    public function returns_null_when_qualification_belongs_to_a_different_organization(): void
    {
        // Arrange
        $id = QualificationId::generate();
        $this->repository->save(Qualification::draft(
            id: $id,
            organizationId: OrganizationId::generate(),
            title: "Someone else's qualification",
            description: null,
            createdBy: AccountId::generate(),
            criteria: QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
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
        $this->repository = $container->get(DoctrineQualificationRepository::class);

        $managerRegistry = $container->get(ManagerRegistry::class);
        $entityManager = $managerRegistry->getManagerForClass(Qualification::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }
}
