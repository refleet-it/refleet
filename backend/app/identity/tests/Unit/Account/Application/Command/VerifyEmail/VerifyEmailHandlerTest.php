<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\VerifyEmail;

use App\Fixtures\Factory\Identity\EmailVerificationTokenFactory;
use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Application\Command\VerifyEmail\VerifyEmailCommand;
use App\Identity\Account\Application\Command\VerifyEmail\VerifyEmailHandler;
use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Exception\EmailVerificationTokenAlreadyUsedException;
use App\Identity\Account\Domain\Account\Exception\EmailVerificationTokenExpiredException;
use App\Identity\Account\Domain\Account\Exception\EmailVerificationTokenNotFoundException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Model\EmailVerificationToken;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\Repository\EmailVerificationTokenRepositoryInterface;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Identity\RefreshToken\Infrastructure\Security\RefreshTokenGenerator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(VerifyEmailHandler::class)]
#[UsesClass(VerifyEmailCommand::class)]
#[UsesClass(TokensDto::class)]
#[UsesClass(EmailVerificationToken::class)]
#[UsesClass(Account::class)]
#[UsesClass(RefreshTokenGenerator::class)]
final class VerifyEmailHandlerTest extends TestCase
{
    use Factories;

    private EmailVerificationTokenRepositoryInterface&MockObject $tokenRepository;

    private AccountRepositoryInterface&MockObject $accountRepository;

    private TokenGenerator&MockObject $tokenGenerator;

    private VerifyEmailHandler $handler;

    #[Test]
    public function throws_when_verification_token_is_not_found(): void
    {
        // Arrange
        $command = new VerifyEmailCommand('missing-token');

        $this->tokenRepository
            ->expects($this->once())
            ->method('findByToken')
            ->with($command->token)
            ->willReturn(null);

        $this->accountRepository->expects($this->never())->method('save');
        $this->tokenGenerator->expects($this->never())->method('generate');

        // Assert
        $this->expectException(EmailVerificationTokenNotFoundException::class);

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function throws_when_verification_token_is_already_used(): void
    {
        // Arrange
        $verificationToken = EmailVerificationTokenFactory::new()
            ->withoutPersisting()
            ->create();
        $verificationToken->markAsUsed();

        $command = new VerifyEmailCommand($verificationToken->token());

        $this->tokenRepository
            ->expects($this->once())
            ->method('findByToken')
            ->with($command->token)
            ->willReturn($verificationToken);

        $this->accountRepository->expects($this->never())->method('save');
        $this->tokenGenerator->expects($this->never())->method('generate');

        // Assert
        $this->expectException(EmailVerificationTokenAlreadyUsedException::class);

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function throws_when_verification_token_is_expired(): void
    {
        // Arrange
        $verificationToken = EmailVerificationTokenFactory::new([
            'expiresAt' => new \DateTimeImmutable('-1 minute'),
        ])
            ->withoutPersisting()
            ->create();
        $command = new VerifyEmailCommand($verificationToken->token());

        $this->tokenRepository
            ->expects($this->once())
            ->method('findByToken')
            ->with($command->token)
            ->willReturn($verificationToken);

        $this->accountRepository->expects($this->never())->method('save');
        $this->tokenGenerator->expects($this->never())->method('generate');

        // Assert
        $this->expectException(EmailVerificationTokenExpiredException::class);

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function verifies_email_marks_token_as_used_saves_account_and_returns_tokens(): void
    {
        // Arrange
        $verificationToken = EmailVerificationTokenFactory::new([
            'expiresAt' => new \DateTimeImmutable('+1 hour'),
        ])
            ->withoutPersisting()
            ->create();
        $account = $verificationToken->account();
        Assert::assertSame(AccountStatusEnum::PENDING_EMAIL_VERIFICATION, $account->status());
        $command = new VerifyEmailCommand($verificationToken->token());
        $expectedJwt = 'jwt-token-value';

        $this->tokenRepository
            ->expects($this->once())
            ->method('findByToken')
            ->with($command->token)
            ->willReturn($verificationToken);

        $this->accountRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Account $savedAccount) use ($account): bool {
                Assert::assertSame($account, $savedAccount);
                Assert::assertTrue($savedAccount->status()->isActive());

                return true;
            }));

        $this->tokenGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($this->identicalTo($account))
            ->willReturn($expectedJwt);

        // Act
        $result = ($this->handler)($command);

        // Assert
        Assert::assertInstanceOf(TokensDto::class, $result);
        Assert::assertSame($expectedJwt, $result->jwtToken);
        Assert::assertNotNull($result->refreshToken);
        Assert::assertNotSame('', $result->refreshToken);
        Assert::assertTrue($verificationToken->isUsed());
        Assert::assertTrue($account->status()->isActive());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->tokenRepository = $this->createMock(EmailVerificationTokenRepositoryInterface::class);
        $this->accountRepository = $this->createMock(AccountRepositoryInterface::class);
        $this->tokenGenerator = $this->createMock(TokenGenerator::class);
        $logger = $this->createStub(LoggerInterface::class);

        $this->handler = new VerifyEmailHandler(
            $this->tokenRepository,
            $this->accountRepository,
            $this->tokenGenerator,
            new RefreshTokenGenerator(),
            $logger,
        );
    }
}
