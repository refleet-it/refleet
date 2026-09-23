<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Infrastructure\Api\AccountReadModel;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(AccountReadModel::class)]
final class AccountReadModelTest extends TestCase
{
    use Factories;

    #[Test]
    public function construct_sets_all_fields_from_account_snapshot(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();

        // Act
        $model = new AccountReadModel(
            id: $account->id()->asString(),
            email: $account->email(),
            role: $account->role()->value,
            status: $account->status()->value,
            createdAt: $account->createdAt()->format('c'),
            updatedAt: $account->updatedAt()->format('c'),
        );

        // Assert
        Assert::assertSame($account->id()->asString(), $model->id);
        Assert::assertSame($account->email(), $model->email);
        Assert::assertSame($account->role()->value, $model->role);
        Assert::assertSame($account->status()->value, $model->status);
        Assert::assertSame($account->createdAt()->format('c'), $model->createdAt);
        Assert::assertSame($account->updatedAt()->format('c'), $model->updatedAt);
    }

    #[Test]
    public function throws_error_when_trying_to_mutate_readonly_property(): void
    {
        // Arrange
        $model = new AccountReadModel(
            id: '550e8400-e29b-41d4-a716-446655440001',
            email: 'user@example.com',
            role: 'employee',
            status: 'active',
            createdAt: '2025-01-01T10:00:00+00:00',
            updatedAt: '2025-01-01T11:00:00+00:00',
        );

        // Act
        $exception = null;
        try {
            $model->email = 'changed@example.com';
        } catch (\Error $error) {
            $exception = $error;
        }

        // Assert
        Assert::assertInstanceOf(\Error::class, $exception);
        Assert::assertSame('user@example.com', $model->email);
    }
}
