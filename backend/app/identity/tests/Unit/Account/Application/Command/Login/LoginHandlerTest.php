<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\Login;

use App\Identity\Account\Application\Command\Login\LoginCommand;
use App\Identity\Account\Application\Command\Login\LoginHandler;
use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\AccountNotActiveException;
use App\Identity\Account\Domain\Account\Exception\InvalidCredentialsException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Infrastructure\Service\PasswordHasher;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Identity\RefreshToken\Domain\RefreshToken\Model\RefreshToken;
use App\Identity\RefreshToken\Domain\RefreshToken\ValueObject\RefreshTokenId;
use App\Identity\RefreshToken\Infrastructure\Security\RefreshTokenGenerator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(LoginHandler::class)]
#[UsesClass(LoginCommand::class)]
#[UsesClass(TokensDto::class)]
#[UsesClass(Account::class)]
#[UsesClass(AccountId::class)]
#[UsesClass(Email::class)]
#[UsesClass(HashedPassword::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(RefreshToken::class)]
#[UsesClass(RefreshTokenId::class)]
#[UsesClass(RefreshTokenGenerator::class)]
final class LoginHandlerTest extends TestCase
{
    private AccountRepositoryInterface&MockObject $accounts;

    private PasswordHasher&MockObject $hasher;

    private TokenGenerator&MockObject $tokens;

    private LoginHandler $handler;

    #[Test]
    public function throws_invalid_credentials_when_account_not_found(): void
    {
        // Arrange
        $command = new LoginCommand('user@example.com', 'Secret123!');

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($command->email)
            ->willReturn(null);

        $this->hasher->expects($this->never())->method('verify');
        $this->tokens->expects($this->never())->method('generate');

        // Assert
        $this->expectException(InvalidCredentialsException::class);

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function throws_account_not_active_when_account_is_inactive(): void
    {
        // Arrange
        $account = $this->createAccount('inactive@example.com', 'hashed-password');
        $account->deactivate();

        $command = new LoginCommand($account->email(), 'Secret123!');

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($command->email)
            ->willReturn($account);

        $this->hasher
            ->expects($this->once())
            ->method('verify')
            ->with($command->plainPassword, $account->passwordHash())
            ->willReturn(true);

        $this->tokens->expects($this->never())->method('generate');

        // Assert
        $this->expectException(AccountNotActiveException::class);

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function throws_invalid_credentials_when_password_is_incorrect(): void
    {
        // Arrange
        $account = $this->createAccount('user@example.com', 'hashed-password');

        $command = new LoginCommand($account->email(), 'WrongPassword!');

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($command->email)
            ->willReturn($account);

        $this->hasher
            ->expects($this->once())
            ->method('verify')
            ->with($command->plainPassword, $account->passwordHash())
            ->willReturn(false);

        $this->tokens->expects($this->never())->method('generate');

        // Assert
        $this->expectException(InvalidCredentialsException::class);

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function returns_tokens_when_credentials_are_valid(): void
    {
        // Arrange
        $account = $this->createAccount('user@example.com', 'hashed-password');

        $command = new LoginCommand($account->email(), 'Secret123!');

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($command->email)
            ->willReturn($account);

        $this->hasher
            ->expects($this->once())
            ->method('verify')
            ->with($command->plainPassword, $account->passwordHash())
            ->willReturn(true);

        $expectedJwt = 'jwt-token-value';

        $this->tokens
            ->expects($this->once())
            ->method('generate')
            ->with($this->identicalTo($account), $this->isNull())
            ->willReturn($expectedJwt);

        // Act
        $result = ($this->handler)($command);

        // Assert
        Assert::assertInstanceOf(TokensDto::class, $result);
        Assert::assertSame($expectedJwt, $result->jwtToken);
        Assert::assertNotEmpty($result->refreshToken);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->accounts = $this->createMock(AccountRepositoryInterface::class);
        $this->hasher = $this->createMock(PasswordHasher::class);
        $this->tokens = $this->createMock(TokenGenerator::class);
        $refreshTokenGenerator = new RefreshTokenGenerator();
        $logger = $this->createStub(LoggerInterface::class);

        $this->handler = new LoginHandler($this->accounts, $this->hasher, $this->tokens, $refreshTokenGenerator, $logger);
    }

    private function createAccount(string $email, string $hashedPassword): Account
    {
        return Account::create(
            AccountId::fromString('11111111-2222-3333-4444-555555555555'),
            Email::fromString($email),
            HashedPassword::fromString($hashedPassword),
            RoleEnum::USER,
            AccountStatusEnum::ACTIVE,
        );
    }
}
