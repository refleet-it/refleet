<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\Register;

use App\Identity\Account\Application\Command\Register\RegisterCommand;
use App\Identity\Account\Application\Command\Register\RegisterHandler;
use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\EmailAlreadyUsedException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Infrastructure\Service\PasswordHasher;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(RegisterHandler::class)]
#[UsesClass(RegisterCommand::class)]
#[UsesClass(TokensDto::class)]
#[UsesClass(Account::class)]
#[UsesClass(AccountId::class)]
#[UsesClass(Email::class)]
#[UsesClass(HashedPassword::class)]
#[UsesClass(RoleEnum::class)]
final class RegisterHandlerTest extends TestCase
{
    private AccountRepositoryInterface&MockObject $accounts;

    private PasswordHasher&MockObject $hasher;

    private LoggerInterface&MockObject $logger;

    private RegisterHandler $handler;

    #[Test]
    public function throws_email_already_used_when_account_exists(): void
    {
        // Arrange
        $command = new RegisterCommand('duplicate@example.com', 'PlainPassword1!', RoleEnum::USER, true);
        $existingAccount = $this->createAccount($command->email);

        $this->hasher
            ->expects($this->once())
            ->method('hash')
            ->with($command->plainPassword)
            ->willReturn('hashed-password');

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($command->email)
            ->willReturn($existingAccount);

        $this->accounts->expects($this->never())->method('save');
        $matcher = $this->exactly(2);

        $this->logger
            ->expects($matcher)
            ->method('info')->willReturnCallback(static function (string $message, array $context = []) use ($matcher, $command): void {
                if (1 === $matcher->numberOfInvocations()) {
                    Assert::assertSame('Started registering account', $message);
                    Assert::assertSame($command->email, $context['email']);
                }

                if (2 === $matcher->numberOfInvocations()) {
                    Assert::assertSame('Registration failed: email already exists', $message);
                    Assert::assertSame($command->email, $context['email']);
                }
            });

        // Assert
        $this->expectException(EmailAlreadyUsedException::class);

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function creates_account_and_returns_tokens_for_new_email(): void
    {
        // Arrange
        $command = new RegisterCommand('newuser@example.com', 'PlainPassword1!', RoleEnum::USER, true);
        $expectedHash = 'hashed-password';

        $this->hasher
            ->expects($this->once())
            ->method('hash')
            ->with($command->plainPassword)
            ->willReturn($expectedHash);

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($command->email)
            ->willReturn(null);

        $capturedAccount = null;

        $this->accounts
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Account $account) use (&$capturedAccount, $command, $expectedHash): bool {
                $capturedAccount = $account;
                Assert::assertSame(\mb_strtolower($command->email), $account->email());
                Assert::assertSame($expectedHash, $account->passwordHash());
                Assert::assertSame($command->role, $account->role());

                return true;
            }));

        $matcher = $this->exactly(2);
        $this->logger
            ->expects($matcher)
            ->method('info')
            ->willReturnCallback(static function (string $message, array $context = []) use ($matcher, $command, &$capturedAccount): void {
                if (1 === $matcher->numberOfInvocations()) {
                    Assert::assertSame('Started registering account', $message);
                    Assert::assertSame($command->email, $context['email']);
                }

                if (2 === $matcher->numberOfInvocations()) {
                    Assert::assertSame('Account registered successfully', $message);
                    Assert::assertSame($command->email, $context['email']);
                    Assert::assertNotNull($capturedAccount);
                    Assert::assertSame($capturedAccount->id()->asString(), $context['accountId']);
                }
            });

        // Act
        ($this->handler)($command);

        // Assert
        Assert::assertNotNull($capturedAccount);
        Assert::assertTrue($capturedAccount->status()->isPendingEmailVerification());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function creates_active_account_when_skip_email_verification_is_true(): void
    {
        // Arrange
        $command = new RegisterCommand('invited@example.com', 'PlainPassword1!', RoleEnum::USER, true, false, true);
        $expectedHash = 'hashed-password';

        $this->hasher->method('hash')->willReturn($expectedHash);
        $this->accounts->method('findByEmail')->willReturn(null);

        $capturedAccount = null;
        $this->accounts
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Account $account) use (&$capturedAccount): bool {
                $capturedAccount = $account;

                return true;
            }));

        $this->logger->method('info');

        // Act
        ($this->handler)($command);

        // Assert
        Assert::assertNotNull($capturedAccount);
        Assert::assertTrue($capturedAccount->status()->isActive());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->accounts = $this->createMock(AccountRepositoryInterface::class);
        $this->hasher = $this->createMock(PasswordHasher::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new RegisterHandler(
            $this->accounts,
            $this->hasher,
            $this->logger,
        );
    }

    private function createAccount(string $email): Account
    {
        return Account::create(
            AccountId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'),
            Email::fromString($email),
            HashedPassword::fromString('stored-hash'),
            RoleEnum::USER,
        );
    }
}
