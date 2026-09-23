<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\ApiKey\Model;

use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Domain\ApiKey\Model\ApiKey;
use App\Identity\Account\Domain\ApiKey\ValueObject\ApiKeyId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiKey::class)]
final class ApiKeyTest extends TestCase
{
    #[Test]
    public function create_is_not_revoked_and_has_no_last_used_at(): void
    {
        // Act
        $apiKey = ApiKey::create(ApiKeyId::generate(), $this->createAccount(), 'CI key', 'ib_abcd1234', 'hash');

        // Assert
        Assert::assertFalse($apiKey->isRevoked());
        Assert::assertNull($apiKey->revokedAt());
        Assert::assertNull($apiKey->lastUsedAt());
    }

    #[Test]
    public function revoke_sets_revoked_at(): void
    {
        // Arrange
        $apiKey = ApiKey::create(ApiKeyId::generate(), $this->createAccount(), 'CI key', 'ib_abcd1234', 'hash');

        // Act
        $apiKey->revoke();

        // Assert
        Assert::assertTrue($apiKey->isRevoked());
        Assert::assertNotNull($apiKey->revokedAt());
    }

    #[Test]
    public function revoke_is_idempotent(): void
    {
        // Arrange
        $apiKey = ApiKey::create(ApiKeyId::generate(), $this->createAccount(), 'CI key', 'ib_abcd1234', 'hash');
        $apiKey->revoke();

        $firstRevokedAt = $apiKey->revokedAt();

        // Act
        $apiKey->revoke();

        // Assert
        Assert::assertSame($firstRevokedAt, $apiKey->revokedAt());
    }

    #[Test]
    public function touch_last_used_sets_last_used_at(): void
    {
        // Arrange
        $apiKey = ApiKey::create(ApiKeyId::generate(), $this->createAccount(), 'CI key', 'ib_abcd1234', 'hash');

        // Act
        $apiKey->touchLastUsed();

        // Assert
        Assert::assertNotNull($apiKey->lastUsedAt());
    }

    private function createAccount(): Account
    {
        return Account::create(
            AccountId::generate(),
            Email::fromString('owner@example.com'),
            HashedPassword::fromString('stored-hash'),
            RoleEnum::USER,
        );
    }
}
