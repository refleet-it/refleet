<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shift\Shift\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\ValueObject\AccountId;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\QualificationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Infrastructure\Persistence\DoctrineShiftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[CoversClass(DoctrineShiftRepository::class)]
final class DoctrineShiftRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineShiftRepository $repository;

    private EntityManagerInterface $entityManager;

    #[Test]
    public function saves_and_finds_a_shift_by_id(): void
    {
        // Arrange
        $id = ShiftId::generate();
        $shift = Shift::draft(
            id: $id,
            organizationId: OrganizationId::generate(),
            title: 'Bump acme/legacy-lib to v3',
            description: 'Removes the deprecated dependency across the fleet.',
            createdBy: AccountId::generate(),
            qualificationId: QualificationId::generate(),
        );

        // Act
        $this->repository->save($shift);
        $this->entityManager->clear();
        $found = $this->repository->findById($id);

        // Assert
        Assert::assertInstanceOf(Shift::class, $found);
        Assert::assertTrue($found->id()->equals($id));
        Assert::assertNotNull($found->qualificationId());
    }

    #[Test]
    public function lists_shifts_for_an_organization_newest_first(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $first = Shift::draft(
            id: ShiftId::generate(),
            organizationId: $organizationId,
            title: 'First shift',
            description: null,
            createdBy: AccountId::generate(),
            qualificationId: null,
        );
        $this->repository->save($first);

        \usleep(1_000);

        $second = Shift::draft(
            id: ShiftId::generate(),
            organizationId: $organizationId,
            title: 'Second shift',
            description: null,
            createdBy: AccountId::generate(),
            qualificationId: null,
        );
        $this->repository->save($second);

        $this->repository->save(Shift::draft(
            id: ShiftId::generate(),
            organizationId: OrganizationId::generate(),
            title: "Someone else's shift",
            description: null,
            createdBy: AccountId::generate(),
            qualificationId: null,
        ));

        $this->entityManager->clear();

        // Act
        $found = $this->repository->findByOrganizationId($organizationId);

        // Assert
        Assert::assertCount(2, $found);
        Assert::assertSame('Second shift', $found[0]->title());
        Assert::assertSame('First shift', $found[1]->title());
    }

    #[Test]
    public function paginates_shifts_across_pages(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        foreach (['Shift A', 'Shift B', 'Shift C'] as $title) {
            $this->repository->save(Shift::draft(
                id: ShiftId::generate(),
                organizationId: $organizationId,
                title: $title,
                description: null,
                createdBy: AccountId::generate(),
                qualificationId: null,
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
    public function keeps_archived_shifts_off_the_live_page_and_lists_them_on_their_own(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $live = Shift::draft(id: ShiftId::generate(), organizationId: $organizationId, title: 'Live', description: null, createdBy: AccountId::generate(), qualificationId: null);
        $archived = Shift::draft(id: ShiftId::generate(), organizationId: $organizationId, title: 'Archived', description: null, createdBy: AccountId::generate(), qualificationId: null);
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
    public function returns_null_when_shift_does_not_exist(): void
    {
        // Act
        $found = $this->repository->findById(ShiftId::generate());

        // Assert
        Assert::assertNull($found);
    }

    #[Test]
    public function finds_shift_by_id_when_it_belongs_to_the_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $id = ShiftId::generate();
        $this->repository->save(Shift::draft(
            id: $id,
            organizationId: $organizationId,
            title: 'Bump acme/legacy-lib to v3',
            description: null,
            createdBy: AccountId::generate(),
            qualificationId: null,
        ));
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findByIdForOrganization($id, $organizationId);

        // Assert
        Assert::assertInstanceOf(Shift::class, $found);
        Assert::assertTrue($found->id()->equals($id));
    }

    #[Test]
    public function returns_null_when_shift_belongs_to_a_different_organization(): void
    {
        // Arrange
        $id = ShiftId::generate();
        $this->repository->save(Shift::draft(
            id: $id,
            organizationId: OrganizationId::generate(),
            title: "Someone else's shift",
            description: null,
            createdBy: AccountId::generate(),
            qualificationId: null,
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
        $this->repository = $container->get(DoctrineShiftRepository::class);

        $managerRegistry = $container->get(ManagerRegistry::class);
        $entityManager = $managerRegistry->getManagerForClass(Shift::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }
}
