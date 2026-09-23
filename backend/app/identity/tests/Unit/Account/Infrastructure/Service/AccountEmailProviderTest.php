<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Service;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Infrastructure\Service\AccountEmailProvider;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(AccountEmailProvider::class)]
final class AccountEmailProviderTest extends TestCase
{
    use Factories;

    private AccountRepositoryInterface&MockObject $accountRepository;

    private AccountEmailProvider $provider;

    #[Test]
    public function returns_account_email_for_existing_user_id(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'email' => Email::fromString('User.Name+1@Example.com'),
        ])->withoutPersisting()->create();

        $userId = UserId::fromString($account->id()->asString());
        $capturedId = null;

        $this->accountRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturnCallback(static function (Id $id) use (&$capturedId, $account): ?\App\Identity\Account\Domain\Account\Model\Account {
                $capturedId = $id;

                return $account;
            });

        // Act
        $email = $this->provider->getEmailByUserId($userId);

        // Assert
        Assert::assertSame($account->email(), $email);
        Assert::assertInstanceOf(Id::class, $capturedId);
        Assert::assertSame($userId->asString(), $capturedId->asString());
    }

    #[Test]
    public function returns_null_when_account_does_not_exist(): void
    {
        // Arrange
        $userId = UserId::generate();
        $capturedId = null;

        $this->accountRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturnCallback(static function (Id $id) use (&$capturedId): ?\App\Identity\Account\Domain\Account\Model\Account {
                $capturedId = $id;

                return null;
            });

        // Act
        $email = $this->provider->getEmailByUserId($userId);

        // Assert
        Assert::assertNull($email);
        Assert::assertInstanceOf(Id::class, $capturedId);
        Assert::assertSame($userId->asString(), $capturedId->asString());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->accountRepository = $this->createMock(AccountRepositoryInterface::class);
        $this->provider = new AccountEmailProvider($this->accountRepository);
    }
}
