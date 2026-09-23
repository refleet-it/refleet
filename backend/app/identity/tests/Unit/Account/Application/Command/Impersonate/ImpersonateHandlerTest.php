<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\Impersonate;

use App\Identity\Account\Application\Command\Impersonate\ImpersonateCommand;
use App\Identity\Account\Application\Command\Impersonate\ImpersonateHandler;
use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\AccountNotFoundException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Identity\RefreshToken\Infrastructure\Security\RefreshTokenGenerator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ImpersonateHandler::class)]
#[UsesClass(ImpersonateCommand::class)]
#[UsesClass(TokensDto::class)]
#[UsesClass(Account::class)]
#[UsesClass(AccountId::class)]
#[UsesClass(Email::class)]
#[UsesClass(HashedPassword::class)]
#[UsesClass(RefreshTokenGenerator::class)]
final class ImpersonateHandlerTest extends TestCase
{
    private AccountRepositoryInterface&MockObject $accounts;

    private TokenGenerator&MockObject $tokens;

    private LoggerInterface&MockObject $logger;

    private ImpersonateHandler $handler;

    #[Test]
    public function returns_tokens_and_logs_on_success(): void
    {
        // Arrange
        $adminId = AccountId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $targetId = AccountId::fromString('11111111-2222-3333-4444-555555555555');
        $target = $this->createAccount($targetId, 'target@example.com', RoleEnum::USER);

        $command = new ImpersonateCommand($adminId, $targetId);

        $this->accounts
            ->expects($this->once())
            ->method('findById')
            ->with($targetId)
            ->willReturn($target);

        $expectedJwt = 'jwt-token-impersonation';

        $this->tokens
            ->expects($this->once())
            ->method('generate')->willReturnCallback(static function ($account) use ($target, $expectedJwt): string {
                Assert::assertInstanceOf(Account::class, $account);
                Assert::assertSame($target->id()->asString(), $account->id()->asString());

                return $expectedJwt;
            });

        $seenStartLog = false;
        $seenSuccessLog = false;

        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(static function (string $message, array $context) use ($adminId, $target, &$seenStartLog, &$seenSuccessLog): void {
                if ('Admin requesting impersonation data' === $message) {
                    Assert::assertSame($adminId->asString(), $context['adminId'] ?? null);
                    Assert::assertSame($target->id()->asString(), $context['targetId'] ?? null);
                    $seenStartLog = true;

                    return;
                }

                if ('Impersonation data provided successfully' === $message) {
                    Assert::assertSame($adminId->asString(), $context['adminId'] ?? null);
                    Assert::assertSame($target->id()->asString(), $context['targetId'] ?? null);
                    Assert::assertSame($target->email(), $context['targetEmail'] ?? null);
                    $seenSuccessLog = true;

                    return;
                }

                Assert::fail('Unexpected log message: '.$message);
            });

        // Act
        $result = ($this->handler)($command);

        // Assert
        Assert::assertInstanceOf(TokensDto::class, $result);
        Assert::assertSame($expectedJwt, $result->jwtToken);
        Assert::assertNotNull($result->refreshToken);
        Assert::assertTrue($seenStartLog, 'Expected start log was not recorded');
        Assert::assertTrue($seenSuccessLog, 'Expected success log was not recorded');
    }

    #[Test]
    public function throws_not_found_and_logs_when_target_missing(): void
    {
        // Arrange
        $adminId = AccountId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $targetId = AccountId::fromString('99999999-8888-7777-6666-555555555555');
        $command = new ImpersonateCommand($adminId, $targetId);

        $this->accounts
            ->expects($this->once())
            ->method('findById')
            ->with($targetId)
            ->willReturn(null);

        $seenStartLog = false;
        $seenNotFoundLog = false;

        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(static function (string $message, array $context) use ($adminId, $targetId, &$seenStartLog, &$seenNotFoundLog): void {
                if ('Admin requesting impersonation data' === $message) {
                    Assert::assertSame($adminId->asString(), $context['adminId'] ?? null);
                    Assert::assertSame($targetId->asString(), $context['targetId'] ?? null);
                    $seenStartLog = true;

                    return;
                }

                if ('Target account not found for impersonation' === $message) {
                    Assert::assertSame($adminId->asString(), $context['adminId'] ?? null);
                    Assert::assertSame($targetId->asString(), $context['targetId'] ?? null);
                    $seenNotFoundLog = true;

                    return;
                }

                Assert::fail('Unexpected log message: '.$message);
            });

        $this->tokens->expects($this->never())->method('generate');

        // Assert
        $this->expectException(AccountNotFoundException::class);

        // Act
        ($this->handler)($command);

        // (teardown) ensure both expected logs were captured
        Assert::assertTrue($seenStartLog);
        Assert::assertTrue($seenNotFoundLog);
    }

    #[Test]
    public function throws_not_found_and_logs_when_target_inactive(): void
    {
        // Arrange
        $adminId = AccountId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
        $targetId = AccountId::fromString('12121212-3434-5656-7878-909090909090');
        $target = $this->createAccount($targetId, 'inactive@example.com', RoleEnum::USER);
        $target->deactivate();

        $command = new ImpersonateCommand($adminId, $targetId);

        $this->accounts
            ->expects($this->once())
            ->method('findById')
            ->with($targetId)
            ->willReturn($target);

        $seenStartLog = false;
        $seenInactiveLog = false;

        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(static function (string $message, array $context) use ($adminId, $targetId, &$seenStartLog, &$seenInactiveLog): void {
                if ('Admin requesting impersonation data' === $message) {
                    Assert::assertSame($adminId->asString(), $context['adminId'] ?? null);
                    Assert::assertSame($targetId->asString(), $context['targetId'] ?? null);
                    $seenStartLog = true;

                    return;
                }

                if ('Target account is not active for impersonation' === $message) {
                    Assert::assertSame($adminId->asString(), $context['adminId'] ?? null);
                    Assert::assertSame($targetId->asString(), $context['targetId'] ?? null);
                    $seenInactiveLog = true;

                    return;
                }

                Assert::fail('Unexpected log message: '.$message);
            });

        $this->tokens->expects($this->never())->method('generate');

        // Note: current handler throws AccountNotFoundException for inactive accounts
        $this->expectException(AccountNotFoundException::class);

        // Act
        ($this->handler)($command);

        // (teardown) ensure both expected logs were captured
        Assert::assertTrue($seenStartLog);
        Assert::assertTrue($seenInactiveLog);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->accounts = $this->createMock(AccountRepositoryInterface::class);
        $this->tokens = $this->createMock(TokenGenerator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new ImpersonateHandler($this->accounts, $this->tokens, new RefreshTokenGenerator(), $this->logger);
    }

    private function createAccount(AccountId $id, string $email, RoleEnum $role): Account
    {
        return Account::create(
            $id,
            Email::fromString($email),
            HashedPassword::fromString('hash'),
            $role,
            AccountStatusEnum::ACTIVE,
        );
    }
}
