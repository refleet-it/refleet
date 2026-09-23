<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity\Account\Infrastructure\Persistence;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Infrastructure\Persistence\DoctrineAccountRepository;
use App\Shared\Domain\ValueObject\CursorListParameters;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[CoversClass(DoctrineAccountRepository::class)]
final class DoctrineAccountRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineAccountRepository $repository;

    private EntityManagerInterface $entityManager;

    #[Test]
    public function save_persists_account_created_without_persisting(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();

        // Act
        $this->repository->save($account);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $found = $this->repository->findById($account->id());

        // Assert
        Assert::assertInstanceOf(Account::class, $found);
        Assert::assertTrue($found->id()->equals($account->id()));
    }

    #[Test]
    public function find_by_email_is_case_insensitive(): void
    {
        // Arrange
        $account = AccountFactory::createOne([
            'email' => Email::fromString('member@example.com'),
        ]);
        $this->entityManager->clear();

        // Act
        $found = $this->repository->findByEmail('MeMbEr@Example.com');

        // Assert
        Assert::assertInstanceOf(Account::class, $found);
        Assert::assertTrue($found->id()->equals($account->id()));
    }

    #[Test]
    public function update_persists_state_change_and_flushes(): void
    {
        // Arrange
        $account = AccountFactory::createOne();
        $this->entityManager->clear();
        $accountToUpdate = $this->repository->findById($account->id());
        Assert::assertInstanceOf(Account::class, $accountToUpdate);
        $accountToUpdate->deactivate();

        // Act
        $this->repository->update($accountToUpdate);
        $this->entityManager->clear();
        $updated = $this->repository->findById($account->id());

        // Assert
        Assert::assertInstanceOf(Account::class, $updated);
        Assert::assertSame(AccountStatusEnum::DEACTIVATED, $updated->status());
    }

    #[Test]
    public function get_paginated_list_returns_only_active_accounts_and_generates_next_cursor(): void
    {
        // Arrange
        AccountFactory::createOne([
            'id' => AccountId::fromString('00000000-0000-0000-0000-000000000001'),
            'email' => Email::fromString('first@example.com'),
            'status' => AccountStatusEnum::ACTIVE,
        ]);
        AccountFactory::createOne([
            'id' => AccountId::fromString('00000000-0000-0000-0000-000000000002'),
            'email' => Email::fromString('second@example.com'),
            'status' => AccountStatusEnum::ACTIVE,
        ]);
        AccountFactory::createOne([
            'id' => AccountId::fromString('00000000-0000-0000-0000-000000000003'),
            'email' => Email::fromString('third@example.com'),
            'status' => AccountStatusEnum::ACTIVE,
        ]);
        AccountFactory::createOne([
            'id' => AccountId::fromString('00000000-0000-0000-0000-000000000004'),
            'email' => Email::fromString('inactive@example.com'),
            'status' => AccountStatusEnum::DEACTIVATED,
        ]);
        $this->entityManager->clear();
        $parameters = CursorListParameters::fromRequest(limit: 2);

        // Act
        $response = $this->repository->getPaginatedList($parameters);
        $items = $response->getItems();
        $decodedCursor = \base64_decode((string) $response->getNextCursor(), true);
        $cursorPayload = false === $decodedCursor ? null : \json_decode($decodedCursor, true);

        // Assert
        Assert::assertCount(2, $items);
        Assert::assertTrue($response->hasNextPage());
        Assert::assertNotNull($response->getNextCursor());
        Assert::assertSame('00000000-0000-0000-0000-000000000003', $items[0]->id()->asString());
        Assert::assertSame('00000000-0000-0000-0000-000000000002', $items[1]->id()->asString());
        Assert::assertIsArray($cursorPayload);
        Assert::assertSame('00000000-0000-0000-0000-000000000001', $cursorPayload['id'] ?? null);
        Assert::assertIsInt($cursorPayload['timestamp'] ?? null);
    }

    #[Test]
    public function get_paginated_list_with_inactive_status_filter_returns_empty_result(): void
    {
        // Arrange
        AccountFactory::createOne([
            'status' => AccountStatusEnum::DEACTIVATED,
        ]);
        AccountFactory::createOne([
            'status' => AccountStatusEnum::ACTIVE,
        ]);
        $this->entityManager->clear();
        $parameters = CursorListParameters::fromRequest(
            limit: 5,
            filters: ['status' => 'inactive'],
        );

        // Act
        $response = $this->repository->getPaginatedList($parameters);

        // Assert
        Assert::assertCount(0, $response->getItems());
        Assert::assertFalse($response->hasNextPage());
        Assert::assertNull($response->getNextCursor());
    }

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->repository = $container->get(DoctrineAccountRepository::class);

        $managerRegistry = $container->get(ManagerRegistry::class);
        $entityManager = $managerRegistry->getManagerForClass(Account::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }
}
