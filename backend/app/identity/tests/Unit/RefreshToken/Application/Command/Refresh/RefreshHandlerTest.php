<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RefreshToken\Application\Command\Refresh;

use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Identity\RefreshToken\Application\Command\Refresh\RefreshCommand;
use App\Identity\RefreshToken\Application\Command\Refresh\RefreshHandler;
use App\Identity\RefreshToken\Domain\RefreshToken\Exception\RefreshTokenAlreadyUsedException;
use App\Identity\RefreshToken\Domain\RefreshToken\Exception\RefreshTokenNotFoundException;
use App\Identity\RefreshToken\Domain\RefreshToken\Model\RefreshToken;
use App\Identity\RefreshToken\Domain\RefreshToken\Repository\RefreshTokenRepositoryInterface;
use App\Identity\RefreshToken\Domain\RefreshToken\ValueObject\RefreshTokenId;
use App\Identity\RefreshToken\Infrastructure\Security\RefreshTokenGenerator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(RefreshHandler::class)]
#[UsesClass(RefreshCommand::class)]
#[UsesClass(TokensDto::class)]
#[UsesClass(RefreshToken::class)]
#[UsesClass(RefreshTokenId::class)]
#[UsesClass(Account::class)]
#[UsesClass(AccountId::class)]
#[UsesClass(Email::class)]
#[UsesClass(HashedPassword::class)]
#[UsesClass(RoleEnum::class)]
#[UsesClass(RefreshTokenGenerator::class)]
final class RefreshHandlerTest extends TestCase
{
    private RefreshTokenRepositoryInterface&MockObject $refreshTokens;

    private TokenGenerator&MockObject $tokens;

    private LoggerInterface&MockObject $logger;

    private RefreshHandler $handler;

    #[Test]
    public function throws_when_refresh_token_not_found(): void
    {
        // Arrange
        $command = new RefreshCommand('missing-token');

        $this->refreshTokens
            ->expects($this->once())
            ->method('findByToken')
            ->with($command->refreshToken)
            ->willReturn(null);

        $this->tokens->expects($this->never())->method('generate');

        $matcher = $this->exactly(2);
        $this->logger
            ->expects($matcher)
            ->method($this->logicalOr('info', 'warning'))
            ->willReturnCallback(function (string $message) use ($matcher): void {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame('Processing token refresh request', $message),
                    2 => $this->assertSame('Token refresh failed: token not found', $message),
                    default => $this->fail('Unexpected log call'),
                };
            });

        // Assert
        $this->expectException(RefreshTokenNotFoundException::class);

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function throws_when_refresh_token_already_used(): void
    {
        // Arrange
        $account = $this->createAccount('user@example.com');

        $refreshToken = RefreshToken::create(
            RefreshTokenId::fromString('11111111-2222-3333-4444-555555555555'),
            $account,
            'existing-refresh',
            new \DateTimeImmutable('+1 day'),
        );
        $refreshToken->setRevoked(true);

        $command = new RefreshCommand($refreshToken->token());

        $this->refreshTokens
            ->expects($this->once())
            ->method('findByToken')
            ->with($command->refreshToken)
            ->willReturn($refreshToken);

        $this->tokens->expects($this->never())->method('generate');

        $matcher = $this->exactly(2);
        $this->logger
            ->expects($matcher)
            ->method($this->logicalOr('info', 'warning'))
            ->willReturnCallback(function (string $message) use ($matcher): void {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame('Processing token refresh request', $message),
                    2 => $this->assertSame('Token refresh failed: token already used', $message),
                    default => $this->fail('Unexpected log call'),
                };
            });

        // Assert
        $this->expectException(RefreshTokenAlreadyUsedException::class);

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function revokes_token_and_returns_new_tokens(): void
    {
        // Arrange
        $account = $this->createAccount('user@example.com');
        $now = new \DateTimeImmutable();

        $existingToken = RefreshToken::create(
            RefreshTokenId::fromString('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'),
            $account,
            'stored-refresh-token',
            new \DateTimeImmutable('+1 day'),
        );

        $command = new RefreshCommand($existingToken->token());

        $expectedJwt = 'jwt-token';

        $this->refreshTokens
            ->expects($this->once())
            ->method('findByToken')
            ->with($command->refreshToken)
            ->willReturn($existingToken);

        $this->tokens
            ->expects($this->once())
            ->method('generate')
            ->with($this->identicalTo($account), $this->isNull())
            ->willReturn($expectedJwt);

        $matcher = $this->exactly(2);
        $this->logger
            ->expects($matcher)
            ->method('info')
            ->willReturnCallback(function (string $message) use ($matcher): void {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame('Processing token refresh request', $message),
                    2 => $this->assertStringStartsWith('Token refresh successful', $message),
                    default => $this->fail('Unexpected log call'),
                };
            });

        // Act
        $result = ($this->handler)($command);

        // Assert
        Assert::assertInstanceOf(TokensDto::class, $result);
        Assert::assertSame($expectedJwt, $result->jwtToken);
        Assert::assertNotEmpty($result->refreshToken);
        Assert::assertTrue($existingToken->isRevoked());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->refreshTokens = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->tokens = $this->createMock(TokenGenerator::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new RefreshHandler($this->refreshTokens, $this->tokens, new RefreshTokenGenerator(), $this->logger);
    }

    private function createAccount(string $email): Account
    {
        return Account::create(
            AccountId::fromString('11111111-2222-3333-4444-555555555555'),
            Email::fromString($email),
            HashedPassword::fromString('hashed-password'),
            RoleEnum::USER,
        );
    }
}
