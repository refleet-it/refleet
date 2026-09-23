<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\ChangePassword;

use App\Identity\Account\Application\Command\ChangePassword\ChangePasswordCommand;
use App\Identity\Account\Application\Command\ChangePassword\ChangePasswordHandler;
use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\AccountNotFoundException;
use App\Identity\Account\Domain\Account\Exception\InvalidCurrentPasswordException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Infrastructure\Service\PasswordHasher;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ChangePasswordHandler::class)]
#[UsesClass(ChangePasswordCommand::class)]
#[UsesClass(Account::class)]
#[UsesClass(AccountId::class)]
#[UsesClass(Email::class)]
#[UsesClass(HashedPassword::class)]
#[UsesClass(RoleEnum::class)]
final class ChangePasswordHandlerTest extends TestCase
{
    private const string ACCOUNT_ID = '11111111-2222-3333-4444-555555555555';

    private AccountRepositoryInterface&MockObject $accounts;

    private PasswordHasher&MockObject $hasher;

    private ChangePasswordHandler $handler;

    #[Test]
    public function throws_account_not_found_when_account_does_not_exist(): void
    {
        // Arrange
        $command = new ChangePasswordCommand(self::ACCOUNT_ID, 'OldPassword1!', 'NewPassword1!');

        $this->accounts
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->hasher->expects($this->never())->method('verify');

        // Assert
        $this->expectException(AccountNotFoundException::class);

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function throws_invalid_current_password_when_it_does_not_match(): void
    {
        // Arrange
        $account = $this->createAccount('hashed-password');
        $command = new ChangePasswordCommand(self::ACCOUNT_ID, 'WrongPassword!', 'NewPassword1!');

        $this->accounts
            ->expects($this->once())
            ->method('findById')
            ->willReturn($account);

        $this->hasher
            ->expects($this->once())
            ->method('verify')
            ->with('WrongPassword!', 'hashed-password')
            ->willReturn(false);

        $this->accounts->expects($this->never())->method('update');

        // Assert
        $this->expectException(InvalidCurrentPasswordException::class);

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function changes_password_when_current_password_is_correct(): void
    {
        // Arrange
        $account = $this->createAccount('old-hashed-password');
        $command = new ChangePasswordCommand(self::ACCOUNT_ID, 'OldPassword1!', 'NewPassword1!');

        $this->accounts
            ->expects($this->once())
            ->method('findById')
            ->willReturn($account);

        $this->hasher
            ->expects($this->once())
            ->method('verify')
            ->with('OldPassword1!', 'old-hashed-password')
            ->willReturn(true);

        $this->hasher
            ->expects($this->once())
            ->method('hash')
            ->with('NewPassword1!')
            ->willReturn('new-hashed-password');

        $this->accounts
            ->expects($this->once())
            ->method('update')
            ->with($this->identicalTo($account));

        // Act
        ($this->handler)($command);

        // Assert
        Assert::assertSame('new-hashed-password', $account->passwordHash());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->accounts = $this->createMock(AccountRepositoryInterface::class);
        $this->hasher = $this->createMock(PasswordHasher::class);
        $logger = $this->createStub(LoggerInterface::class);

        $this->handler = new ChangePasswordHandler($this->accounts, $this->hasher, $logger);
    }

    private function createAccount(string $hashedPassword): Account
    {
        return Account::create(
            AccountId::fromString(self::ACCOUNT_ID),
            Email::fromString('user@example.com'),
            HashedPassword::fromString($hashedPassword),
            RoleEnum::USER,
            AccountStatusEnum::ACTIVE,
        );
    }
}
