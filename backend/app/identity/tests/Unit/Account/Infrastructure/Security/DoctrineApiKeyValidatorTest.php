<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Security;

use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Domain\ApiKey\Model\ApiKey;
use App\Identity\Account\Domain\ApiKey\Repository\ApiKeyRepositoryInterface;
use App\Identity\Account\Domain\ApiKey\ValueObject\ApiKeyId;
use App\Identity\Account\Infrastructure\Security\DoctrineApiKeyValidator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineApiKeyValidator::class)]
final class DoctrineApiKeyValidatorTest extends TestCase
{
    private ApiKeyRepositoryInterface&MockObject $repository;

    private DoctrineApiKeyValidator $validator;

    #[Test]
    public function it_validates_a_valid_key_and_touches_last_used(): void
    {
        // Arrange
        $account = $this->createAccount(AccountStatusEnum::ACTIVE);
        $apiKey = ApiKey::create(ApiKeyId::generate(), $account, 'CI key', 'ib_abcd1234', \hash('sha256', 'ib_the-plain-token'));
        $this->repository->method('findByHashedSecret')->willReturn($apiKey);
        $this->repository->expects($this->once())->method('save')->with($apiKey);

        // Act
        $validated = $this->validator->validate('ib_the-plain-token');

        // Assert
        Assert::assertNotNull($validated);
        Assert::assertSame($account->id()->asString(), $validated->accountId);
        Assert::assertSame($account->email(), $validated->email);
        Assert::assertSame([$account->role()->toSymfonyRole()], $validated->symfonyRoles);
        Assert::assertSame($apiKey->id()->asString(), $validated->apiKeyId);
        Assert::assertNotNull($apiKey->lastUsedAt());
    }

    #[Test]
    public function it_returns_null_for_a_revoked_key(): void
    {
        // Arrange
        $account = $this->createAccount(AccountStatusEnum::ACTIVE);
        $apiKey = ApiKey::create(ApiKeyId::generate(), $account, 'CI key', 'ib_abcd1234', \hash('sha256', 'ib_the-plain-token'));
        $apiKey->revoke();
        $this->repository->method('findByHashedSecret')->willReturn($apiKey);
        $this->repository->expects($this->never())->method('save');

        // Act
        $validated = $this->validator->validate('ib_the-plain-token');

        // Assert
        Assert::assertNull($validated);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function it_returns_null_for_an_unknown_key(): void
    {
        // Arrange
        $this->repository->method('findByHashedSecret')->willReturn(null);

        // Act
        $validated = $this->validator->validate('ib_unknown-token');

        // Assert
        Assert::assertNull($validated);
    }

    #[Test]
    public function it_returns_null_for_a_key_belonging_to_an_inactive_account(): void
    {
        // Arrange
        $account = $this->createAccount(AccountStatusEnum::SUSPENDED);
        $apiKey = ApiKey::create(ApiKeyId::generate(), $account, 'CI key', 'ib_abcd1234', \hash('sha256', 'ib_the-plain-token'));
        $this->repository->method('findByHashedSecret')->willReturn($apiKey);
        $this->repository->expects($this->never())->method('save');

        // Act
        $validated = $this->validator->validate('ib_the-plain-token');

        // Assert
        Assert::assertNull($validated);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = $this->createMock(ApiKeyRepositoryInterface::class);
        $this->validator = new DoctrineApiKeyValidator($this->repository);
    }

    private function createAccount(AccountStatusEnum $status): Account
    {
        return Account::create(
            AccountId::generate(),
            Email::fromString('owner@example.com'),
            HashedPassword::fromString('stored-hash'),
            RoleEnum::USER,
            $status,
        );
    }
}
