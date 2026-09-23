<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\RequestPasswordReset;

use App\Identity\Account\Application\Command\RequestPasswordReset\RequestPasswordResetCommand;
use App\Identity\Account\Application\Command\RequestPasswordReset\RequestPasswordResetHandler;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Model\PasswordResetToken;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(RequestPasswordResetHandler::class)]
#[UsesClass(RequestPasswordResetCommand::class)]
#[UsesClass(PasswordResetToken::class)]
#[UsesClass(Id::class)]
final class RequestPasswordResetHandlerTest extends TestCase
{
    private AccountRepositoryInterface&MockObject $accounts;

    private LoggerInterface&MockObject $logger;

    private RequestPasswordResetHandler $handler;

    #[Test]
    public function logs_when_email_not_found_and_does_nothing(): void
    {
        // Arrange
        $email = 'missing@example.com';
        $command = new RequestPasswordResetCommand($email);

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);

        $this->accounts->expects($this->never())->method('update');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Password reset requested for non-existent email: '.$email);

        // Act
        ($this->handler)($command);

        // Assert via mock expectations
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function logs_when_account_deleted_and_does_not_generate_token(): void
    {
        // Arrange
        $email = 'deleted@example.com';
        $command = new RequestPasswordResetCommand($email);

        $account = $this->createStub(Account::class);
        $account->method('isDeleted')->willReturn(true);

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($account);
        $this->accounts->expects($this->never())->method('update');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Password reset requested for deleted account: '.$email);

        // Act
        ($this->handler)($command);

        // Assert via mock expectations
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function generates_token_updates_and_logs_for_active_account(): void
    {
        // Arrange
        $email = 'active@example.com';
        $command = new RequestPasswordResetCommand($email);

        $account = $this->createMock(Account::class);
        $account->method('isDeleted')->willReturn(false);

        $token = PasswordResetToken::create(
            Id::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'),
            $account,
            'reset-token',
            new \DateTimeImmutable('+1 hour'),
        );

        $account
            ->expects($this->once())
            ->method('requestPasswordReset')
            ->willReturn($token);

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($account);

        $this->accounts
            ->expects($this->once())
            ->method('update')
            ->with($account);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Password reset token generated for account: '.$email);

        // Act
        ($this->handler)($command);

        // Assert via mock expectations
        $this->addToAssertionCount(1);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->accounts = $this->createMock(AccountRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new RequestPasswordResetHandler($this->accounts, $this->logger);
    }
}
