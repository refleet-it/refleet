<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shift\Shift\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ProjectId;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use App\Shift\Shift\Infrastructure\Persistence\DoctrineShiftTargetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[CoversClass(DoctrineShiftTargetRepository::class)]
final class DoctrineShiftTargetRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineShiftTargetRepository $repository;

    private EntityManagerInterface $entityManager;

    #[Test]
    public function saves_and_finds_a_shift_target_by_id(): void
    {
        // Arrange
        $id = ShiftTargetId::generate();
        $target = $this->createTarget($id, ShiftId::generate(), OrganizationId::generate());

        // Act
        $this->repository->save($target);
        $this->entityManager->clear();
        $found = $this->repository->findById($id);

        // Assert
        Assert::assertInstanceOf(ShiftTarget::class, $found);
        Assert::assertTrue($found->id()->equals($id));
    }

    #[Test]
    public function saves_a_batch_and_finds_all_targets_for_a_shift(): void
    {
        // Arrange
        $shiftId = ShiftId::generate();
        $organizationId = OrganizationId::generate();

        $targets = [
            $this->createTarget(ShiftTargetId::generate(), $shiftId, $organizationId),
            $this->createTarget(ShiftTargetId::generate(), $shiftId, $organizationId),
            $this->createTarget(ShiftTargetId::generate(), $shiftId, $organizationId),
        ];

        // Act
        $this->repository->saveAll($targets);
        $this->entityManager->clear();
        $found = $this->repository->getPaginatedListByShiftId($shiftId, PaginationParameters::fromRequest());

        // Assert
        Assert::assertCount(3, $found->getItems());
    }

    #[Test]
    public function filters_targets_by_status(): void
    {
        // Arrange
        $shiftId = ShiftId::generate();
        $organizationId = OrganizationId::generate();

        $pending = $this->createTarget(ShiftTargetId::generate(), $shiftId, $organizationId);

        $inProgress = $this->createTarget(ShiftTargetId::generate(), $shiftId, $organizationId);
        $inProgress->startChange(ShiftTargetId::generate()->asString());

        $this->repository->saveAll([$pending, $inProgress]);
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findByShiftIdAndStatuses($shiftId, [ShiftTargetStatusEnum::PENDING_CHANGE]);

        // Assert
        Assert::assertCount(1, $found);
        Assert::assertTrue($found[0]->id()->equals($pending->id()));
    }

    #[Test]
    public function finds_non_terminal_targets_for_cascade_cancellation(): void
    {
        // Arrange
        $shiftId = ShiftId::generate();
        $organizationId = OrganizationId::generate();

        $pending = $this->createTarget(ShiftTargetId::generate(), $shiftId, $organizationId);

        $completed = $this->createTarget(ShiftTargetId::generate(), $shiftId, $organizationId);
        $completed->startChange(ShiftTargetId::generate()->asString());
        $completed->recordChangeSuccess('Bumped', 'branch', 'runner-1', 'https://gitlab.example/mr/1', '1');
        $completed->recordMergeRequestMerged();

        $this->repository->saveAll([$pending, $completed]);
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findNonTerminalByShiftId($shiftId);

        // Assert
        Assert::assertCount(1, $found);
        Assert::assertTrue($found[0]->id()->equals($pending->id()));
    }

    #[Test]
    public function counts_targets_by_statuses(): void
    {
        // Arrange
        $shiftId = ShiftId::generate();
        $organizationId = OrganizationId::generate();

        $this->repository->saveAll([
            $this->createTarget(ShiftTargetId::generate(), $shiftId, $organizationId),
            $this->createTarget(ShiftTargetId::generate(), $shiftId, $organizationId),
        ]);
        $this->entityManager->clear();

        // Act
        $count = $this->repository->countByShiftIdAndStatuses($shiftId, [ShiftTargetStatusEnum::PENDING_CHANGE]);

        // Assert
        Assert::assertSame(2, $count);
    }

    #[Test]
    public function computes_status_breakdown_for_a_shift(): void
    {
        // Arrange
        $shiftId = ShiftId::generate();
        $organizationId = OrganizationId::generate();

        $pendingOne = $this->createTarget(ShiftTargetId::generate(), $shiftId, $organizationId);
        $pendingTwo = $this->createTarget(ShiftTargetId::generate(), $shiftId, $organizationId);

        $inProgress = $this->createTarget(ShiftTargetId::generate(), $shiftId, $organizationId);
        $inProgress->startChange(ShiftTargetId::generate()->asString());

        $this->repository->saveAll([$pendingOne, $pendingTwo, $inProgress]);
        $this->entityManager->clear();

        // Act
        $breakdown = $this->repository->statusBreakdown($shiftId);

        // Assert
        Assert::assertSame(2, $breakdown[ShiftTargetStatusEnum::PENDING_CHANGE->value] ?? null);
        Assert::assertSame(1, $breakdown[ShiftTargetStatusEnum::CHANGE_IN_PROGRESS->value] ?? null);
    }

    #[Test]
    public function returns_null_when_shift_target_does_not_exist(): void
    {
        // Act
        $found = $this->repository->findById(ShiftTargetId::generate());

        // Assert
        Assert::assertNull($found);
    }

    #[Test]
    public function finds_a_shift_target_by_its_merge_request_url(): void
    {
        // Arrange
        $id = ShiftTargetId::generate();
        $target = $this->createTarget($id, ShiftId::generate(), OrganizationId::generate());
        $target->startChange(ShiftTargetId::generate()->asString());
        $target->recordChangeSuccess('applied change', 'refleet/bump-lib', 'runner-1', 'https://gitlab.com/acme/payments-service/-/merge_requests/7', '7');

        $this->repository->save($target);
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findByMergeRequestUrl('https://gitlab.com/acme/payments-service/-/merge_requests/7');
        $missing = $this->repository->findByMergeRequestUrl('https://gitlab.com/acme/other/-/merge_requests/1');

        // Assert
        Assert::assertInstanceOf(ShiftTarget::class, $found);
        Assert::assertTrue($found->id()->equals($id));
        Assert::assertNull($missing);
    }

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->repository = $container->get(DoctrineShiftTargetRepository::class);

        $managerRegistry = $container->get(ManagerRegistry::class);
        $entityManager = $managerRegistry->getManagerForClass(ShiftTarget::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    private function createTarget(ShiftTargetId $id, ShiftId $shiftId, OrganizationId $organizationId): ShiftTarget
    {
        return ShiftTarget::create(
            id: $id,
            shiftId: $shiftId,
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
