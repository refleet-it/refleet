<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification\NotificationPreference\Infrastructure\Persistence;

use App\Fixtures\Factory\Notification\NotificationPreferenceFactory;
use App\Notification\NotificationPreference\Domain\Model\NotificationPreference;
use App\Notification\NotificationPreference\Infrastructure\Persistence\NotificationPreferenceRepository;
use App\Shared\Domain\ValueObject\Id;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(NotificationPreferenceRepository::class)]
final class NotificationPreferenceRepositoryTest extends TestCase
{
    use Factories;

    private EntityManagerInterface&MockObject $entityManager;

    private NotificationPreferenceRepository&MockObject $repository;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function save_persists_and_flushes_preference_created_without_persisting(): void
    {
        // Arrange
        $preference = NotificationPreferenceFactory::new()->withoutPersisting()->create();
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->identicalTo($preference));
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $this->repository->save($preference);

        // Assert
        Assert::assertInstanceOf(NotificationPreference::class, $preference);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function update_flushes_without_persisting_entity_again(): void
    {
        // Arrange
        $preference = NotificationPreferenceFactory::new()->withoutPersisting()->create();
        $this->entityManager
            ->expects($this->never())
            ->method('persist');
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $this->repository->update($preference);

        // Assert
        Assert::assertInstanceOf(NotificationPreference::class, $preference);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function find_by_id_returns_preference_for_matching_identifier(): void
    {
        // Arrange
        $id = Id::generate();
        $preference = NotificationPreferenceFactory::new()->withoutPersisting()->create();
        $this->repository
            ->expects($this->once())
            ->method('find')
            ->with($this->identicalTo($id->asString()))
            ->willReturn($preference);

        // Act
        $found = $this->repository->findById($id);

        // Assert
        Assert::assertSame($preference, $found);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function find_by_id_returns_null_when_repository_has_no_match(): void
    {
        // Arrange
        $id = Id::generate();
        $this->repository
            ->expects($this->once())
            ->method('find')
            ->with($this->identicalTo($id->asString()))
            ->willReturn(null);

        // Act
        $found = $this->repository->findById($id);

        // Assert
        Assert::assertNull($found);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function find_by_user_id_and_type_uses_exact_criteria_and_returns_match(): void
    {
        // Arrange
        $userId = Id::generate();
        $notificationType = 'employee-invitation';
        $preference = NotificationPreferenceFactory::new()->withoutPersisting()->create();
        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($this->identicalTo([
                'userId' => $userId->asString(),
                'notificationType' => $notificationType,
            ]))
            ->willReturn($preference);

        // Act
        $found = $this->repository->findByUserIdAndType($userId, $notificationType);

        // Assert
        Assert::assertSame($preference, $found);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function find_by_user_id_and_type_returns_null_when_missing(): void
    {
        // Arrange
        $userId = Id::generate();
        $notificationType = 'password-reset';
        $this->repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($this->identicalTo([
                'userId' => $userId->asString(),
                'notificationType' => $notificationType,
            ]))
            ->willReturn(null);

        // Act
        $found = $this->repository->findByUserIdAndType($userId, $notificationType);

        // Assert
        Assert::assertNull($found);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function find_by_user_id_returns_all_preferences_for_given_user(): void
    {
        // Arrange
        $userId = Id::generate();
        $first = NotificationPreferenceFactory::new()->withoutPersisting()->create();
        $second = NotificationPreferenceFactory::new()->withoutPersisting()->create();
        $expected = [$first, $second];
        $this->repository
            ->expects($this->once())
            ->method('findBy')
            ->with($this->identicalTo(['userId' => $userId->asString()]))
            ->willReturn($expected);

        // Act
        $found = $this->repository->findByUserId($userId);

        // Assert
        Assert::assertSame($expected, $found);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function find_by_user_id_returns_empty_array_when_user_has_no_preferences(): void
    {
        // Arrange
        $userId = Id::generate();
        $this->repository
            ->expects($this->once())
            ->method('findBy')
            ->with($this->identicalTo(['userId' => $userId->asString()]))
            ->willReturn([]);

        // Act
        $found = $this->repository->findByUserId($userId);

        // Assert
        Assert::assertSame([], $found);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->getMockBuilder(NotificationPreferenceRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEntityManager', 'find', 'findOneBy', 'findBy'])
            ->getMock();
        $this->repository
            ->method('getEntityManager')
            ->willReturn($this->entityManager);
    }
}
