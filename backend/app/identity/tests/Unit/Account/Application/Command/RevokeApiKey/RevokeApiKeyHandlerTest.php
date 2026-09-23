<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\RevokeApiKey;

use App\Identity\Account\Application\Command\RevokeApiKey\RevokeApiKeyCommand;
use App\Identity\Account\Application\Command\RevokeApiKey\RevokeApiKeyHandler;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Domain\ApiKey\Exception\ApiKeyNotFoundException;
use App\Identity\Account\Domain\ApiKey\Model\ApiKey;
use App\Identity\Account\Domain\ApiKey\Repository\ApiKeyRepositoryInterface;
use App\Identity\Account\Domain\ApiKey\ValueObject\ApiKeyId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RevokeApiKeyHandler::class)]
final class RevokeApiKeyHandlerTest extends TestCase
{
    private ApiKeyRepositoryInterface&MockObject $repository;

    private RevokeApiKeyHandler $handler;

    #[Test]
    public function it_revokes_the_key(): void
    {
        // Arrange
        $account = $this->createAccount();
        $apiKey = ApiKey::create(ApiKeyId::generate(), $account, 'CI key', 'ib_abcd1234', 'hash');
        $this->repository->method('findById')->willReturn($apiKey);
        $this->repository->expects($this->once())->method('save')->with($apiKey);

        // Act
        ($this->handler)(new RevokeApiKeyCommand($account->id()->asString(), $apiKey->id()->asString()));

        // Assert
        Assert::assertTrue($apiKey->isRevoked());
    }

    #[Test]
    public function it_throws_when_revoking_a_key_belonging_to_a_different_account(): void
    {
        // Arrange - an account must not be able to revoke another account's key by guessing its id.
        $owner = $this->createAccount();
        $apiKey = ApiKey::create(ApiKeyId::generate(), $owner, 'CI key', 'ib_abcd1234', 'hash');
        $this->repository->method('findById')->willReturn($apiKey);
        $this->repository->expects($this->never())->method('save');

        // Assert
        $this->expectException(ApiKeyNotFoundException::class);

        // Act
        ($this->handler)(new RevokeApiKeyCommand(AccountId::generate()->asString(), $apiKey->id()->asString()));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function it_throws_when_the_key_does_not_exist(): void
    {
        // Arrange
        $this->repository->method('findById')->willReturn(null);

        // Assert
        $this->expectException(ApiKeyNotFoundException::class);

        // Act
        ($this->handler)(new RevokeApiKeyCommand(AccountId::generate()->asString(), ApiKeyId::generate()->asString()));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->repository = $this->createMock(ApiKeyRepositoryInterface::class);
        $this->handler = new RevokeApiKeyHandler($this->repository);
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
