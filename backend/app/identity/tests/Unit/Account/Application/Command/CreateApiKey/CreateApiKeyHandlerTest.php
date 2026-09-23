<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\CreateApiKey;

use App\Identity\Account\Application\Command\CreateApiKey\CreateApiKeyCommand;
use App\Identity\Account\Application\Command\CreateApiKey\CreateApiKeyHandler;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\AccountNotFoundException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Domain\ApiKey\Model\ApiKey;
use App\Identity\Account\Domain\ApiKey\Repository\ApiKeyRepositoryInterface;
use App\Identity\Account\Infrastructure\Security\ApiKeyTokenGenerator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateApiKeyHandler::class)]
final class CreateApiKeyHandlerTest extends TestCase
{
    private AccountRepositoryInterface&Stub $accounts;

    private ApiKeyRepositoryInterface&MockObject $apiKeys;

    private CreateApiKeyHandler $handler;

    #[Test]
    public function it_returns_the_plaintext_token_that_hashes_to_the_persisted_secret(): void
    {
        // Arrange
        $account = $this->createAccount();
        $this->accounts->method('findById')->willReturn($account);

        $savedApiKey = null;
        $this->apiKeys
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (ApiKey $apiKey) use (&$savedApiKey): bool {
                $savedApiKey = $apiKey;

                return true;
            }));

        // Act
        $result = ($this->handler)(new CreateApiKeyCommand($account->id()->asString(), 'CI key'));

        // Assert
        Assert::assertNotNull($savedApiKey);
        Assert::assertStringStartsWith(ApiKeyTokenGenerator::PREFIX, $result->token);
        Assert::assertSame($result->prefix, \substr($result->token, 0, \strlen($result->prefix)));
        Assert::assertSame('CI key', $result->name);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function it_throws_when_the_account_does_not_exist(): void
    {
        // Arrange
        $this->accounts->method('findById')->willReturn(null);

        // Assert
        $this->expectException(AccountNotFoundException::class);

        // Act
        ($this->handler)(new CreateApiKeyCommand(AccountId::generate()->asString(), 'CI key'));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->accounts = $this->createStub(AccountRepositoryInterface::class);
        $this->apiKeys = $this->createMock(ApiKeyRepositoryInterface::class);

        $this->handler = new CreateApiKeyHandler($this->accounts, $this->apiKeys, new ApiKeyTokenGenerator());
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
