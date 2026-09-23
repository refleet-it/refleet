<?php

declare(strict_types=1);

namespace App\Tests\Integration\Qualification\Qualification\Infrastructure\Persistence;

use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationScore;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\ProjectId;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use App\Qualification\Qualification\Infrastructure\Persistence\DoctrineQualificationTargetRepository;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[CoversClass(DoctrineQualificationTargetRepository::class)]
final class DoctrineQualificationTargetRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineQualificationTargetRepository $repository;

    private EntityManagerInterface $entityManager;

    #[Test]
    public function saves_and_finds_a_qualification_target_by_id(): void
    {
        // Arrange
        $id = QualificationTargetId::generate();
        $target = $this->createTarget($id, QualificationId::generate(), OrganizationId::generate());

        // Act
        $this->repository->save($target);
        $this->entityManager->clear();
        $found = $this->repository->findById($id);

        // Assert
        Assert::assertInstanceOf(QualificationTarget::class, $found);
        Assert::assertTrue($found->id()->equals($id));
    }

    #[Test]
    public function saves_a_batch_and_finds_all_targets_for_a_qualification(): void
    {
        // Arrange
        $qualificationId = QualificationId::generate();
        $organizationId = OrganizationId::generate();

        $targets = [
            $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId),
            $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId),
            $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId),
        ];

        // Act
        $this->repository->saveAll($targets);
        $this->entityManager->clear();
        $found = $this->repository->findByQualificationId($qualificationId);

        // Assert
        Assert::assertCount(3, $found);
    }

    #[Test]
    public function filters_targets_by_status(): void
    {
        // Arrange
        $qualificationId = QualificationId::generate();
        $organizationId = OrganizationId::generate();

        $pending = $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId);

        $inProgress = $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId);
        $inProgress->start(QualificationTargetId::generate()->asString());

        $this->repository->saveAll([$pending, $inProgress]);
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findByQualificationIdAndStatuses($qualificationId, [QualificationTargetStatusEnum::PENDING]);

        // Assert
        Assert::assertCount(1, $found);
        Assert::assertTrue($found[0]->id()->equals($pending->id()));
    }

    #[Test]
    public function finds_only_non_terminal_targets(): void
    {
        // Arrange
        $qualificationId = QualificationId::generate();
        $organizationId = OrganizationId::generate();

        $pending = $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId);

        $qualified = $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId);
        $qualified->start(QualificationTargetId::generate()->asString());
        $qualified->recordSuccess(QualificationScore::fromInt(5), 'Qualified', 'runner-1');

        $this->repository->saveAll([$pending, $qualified]);
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findNonTerminalByQualificationId($qualificationId);

        // Assert
        Assert::assertCount(1, $found);
        Assert::assertTrue($found[0]->id()->equals($pending->id()));
    }

    #[Test]
    public function counts_targets_by_statuses(): void
    {
        // Arrange
        $qualificationId = QualificationId::generate();
        $organizationId = OrganizationId::generate();

        $this->repository->saveAll([
            $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId),
            $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId),
        ]);
        $this->entityManager->clear();

        // Act
        $count = $this->repository->countByQualificationIdAndStatuses($qualificationId, [QualificationTargetStatusEnum::PENDING]);

        // Assert
        Assert::assertSame(2, $count);
    }

    #[Test]
    public function computes_status_breakdown_for_a_qualification(): void
    {
        // Arrange
        $qualificationId = QualificationId::generate();
        $organizationId = OrganizationId::generate();

        $pendingOne = $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId);
        $pendingTwo = $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId);

        $inProgress = $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId);
        $inProgress->start(QualificationTargetId::generate()->asString());

        $this->repository->saveAll([$pendingOne, $pendingTwo, $inProgress]);
        $this->entityManager->clear();

        // Act
        $breakdown = $this->repository->statusBreakdown($qualificationId);

        // Assert
        Assert::assertSame(2, $breakdown[QualificationTargetStatusEnum::PENDING->value] ?? null);
        Assert::assertSame(1, $breakdown[QualificationTargetStatusEnum::IN_PROGRESS->value] ?? null);
    }

    #[Test]
    public function paginates_targets_for_a_qualification_and_filters_by_status(): void
    {
        // Arrange
        $qualificationId = QualificationId::generate();
        $organizationId = OrganizationId::generate();

        $targets = [
            $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId),
            $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId),
            $this->createTarget(QualificationTargetId::generate(), $qualificationId, $organizationId),
        ];
        $targets[0]->start(QualificationTargetId::generate()->asString());

        $this->repository->saveAll($targets);
        $this->entityManager->clear();

        // Act
        $firstPage = $this->repository->getPaginatedListByQualificationId($qualificationId, PaginationParameters::fromRequest(page: 1, limit: 2));
        $secondPage = $this->repository->getPaginatedListByQualificationId($qualificationId, PaginationParameters::fromRequest(page: 2, limit: 2));
        $filtered = $this->repository->getPaginatedListByQualificationId($qualificationId, PaginationParameters::fromRequest(), QualificationTargetStatusEnum::IN_PROGRESS);

        // Assert
        Assert::assertCount(2, $firstPage->getItems());
        Assert::assertSame(3, $firstPage->getTotalItems());
        Assert::assertTrue($firstPage->hasNextPage());
        Assert::assertCount(1, $secondPage->getItems());
        Assert::assertFalse($secondPage->hasNextPage());
        Assert::assertCount(1, $filtered->getItems());
        Assert::assertTrue($filtered->getItems()[0]->id()->equals($targets[0]->id()));
    }

    #[Test]
    public function returns_null_when_qualification_target_does_not_exist(): void
    {
        // Act
        $found = $this->repository->findById(QualificationTargetId::generate());

        // Assert
        Assert::assertNull($found);
    }

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->repository = $container->get(DoctrineQualificationTargetRepository::class);

        $managerRegistry = $container->get(ManagerRegistry::class);
        $entityManager = $managerRegistry->getManagerForClass(QualificationTarget::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    private function createTarget(QualificationTargetId $id, QualificationId $qualificationId, OrganizationId $organizationId): QualificationTarget
    {
        return QualificationTarget::create(
            id: $id,
            qualificationId: $qualificationId,
            organizationId: $organizationId,
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot(
                externalId: '48210942',
                path: 'backend-team/payments-service',
                name: 'Payments Service',
                defaultBranch: 'main',
            ),
        );
    }
}
