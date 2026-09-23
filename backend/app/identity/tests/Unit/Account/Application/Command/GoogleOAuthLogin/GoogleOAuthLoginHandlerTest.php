<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\GoogleOAuthLogin;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Application\Command\GoogleOAuthLogin\GoogleOAuthLoginCommand;
use App\Identity\Account\Application\Command\GoogleOAuthLogin\GoogleOAuthLoginHandler;
use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Enum\OAuthProvider;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\AccountNotActiveException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Policy\AccountMergingPolicy;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
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

#[CoversClass(GoogleOAuthLoginHandler::class)]
#[UsesClass(GoogleOAuthLoginCommand::class)]
#[UsesClass(TokensDto::class)]
#[UsesClass(AccountMergingPolicy::class)]
#[UsesClass(RefreshTokenGenerator::class)]
final class GoogleOAuthLoginHandlerTest extends TestCase
{
    use Factories;

    private AccountRepositoryInterface&MockObject $accounts;

    private TokenGenerator&MockObject $tokenGenerator;

    private GoogleOAuthLoginHandler $handler;

    #[Test]
    public function creates_new_google_account_and_returns_tokens_when_email_does_not_exist(): void
    {
        // Arrange
        $command = new GoogleOAuthLoginCommand(
            email: 'New.Manager@Example.com',
            googleId: 'google-user-1',
            marketingConsent: true,
        );

        $savedAccount = null;

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($command->email)
            ->willReturn(null);

        $this->accounts
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Account $account) use (&$savedAccount): bool {
                $savedAccount = $account;

                Assert::assertSame('new.manager@example.com', $account->email());
                Assert::assertTrue($account->isActive());
                Assert::assertSame(RoleEnum::USER, $account->role());
                Assert::assertTrue($account->hasOAuthProvider(OAuthProvider::GOOGLE));
                Assert::assertTrue($account->hasMarketingConsent());

                return true;
            }));

        $this->tokenGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturnCallback(static function (Account $account) use (&$savedAccount): string {
                Assert::assertSame($savedAccount, $account);

                return 'jwt-created-account';
            });

        // Act
        $result = ($this->handler)($command);

        // Assert
        Assert::assertInstanceOf(TokensDto::class, $result);
        Assert::assertSame('jwt-created-account', $result->jwtToken);
        Assert::assertIsString($result->refreshToken);
        Assert::assertNotSame('', $result->refreshToken);
    }

    #[Test]
    public function merges_google_provider_and_auto_verifies_pending_account(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'status' => AccountStatusEnum::PENDING_EMAIL_VERIFICATION,
        ])->withoutPersisting()->create();

        $command = new GoogleOAuthLoginCommand(
            email: $account->email(),
            googleId: 'google-user-2',
            marketingConsent: false,
        );

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($command->email)
            ->willReturn($account);

        $this->accounts
            ->expects($this->once())
            ->method('save')
            ->with($this->identicalTo($account));

        $this->tokenGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($this->identicalTo($account))
            ->willReturn('jwt-merged-account');

        // Act
        $result = ($this->handler)($command);

        // Assert
        Assert::assertInstanceOf(TokensDto::class, $result);
        Assert::assertSame('jwt-merged-account', $result->jwtToken);
        Assert::assertTrue($account->hasOAuthProvider(OAuthProvider::GOOGLE));
        Assert::assertTrue($account->status()->isActive());
        Assert::assertNotSame('', $result->refreshToken);
    }

    #[Test]
    public function logs_in_existing_active_account_with_google_provider_without_re_saving(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'status' => AccountStatusEnum::ACTIVE,
        ])->withoutPersisting()->create();
        $account->addOAuthProvider(OAuthProvider::GOOGLE);

        $command = new GoogleOAuthLoginCommand(
            email: $account->email(),
            googleId: 'google-user-3',
            marketingConsent: false,
        );

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($command->email)
            ->willReturn($account);

        $this->accounts
            ->expects($this->never())
            ->method('save');

        $this->tokenGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($this->identicalTo($account))
            ->willReturn('jwt-existing-google');

        // Act
        $result = ($this->handler)($command);

        // Assert
        Assert::assertSame('jwt-existing-google', $result->jwtToken);
        Assert::assertTrue($account->hasOAuthProvider(OAuthProvider::GOOGLE));
        Assert::assertTrue($account->status()->isActive());
        Assert::assertNotSame('', $result->refreshToken);
    }

    #[Test]
    public function throws_when_deactivated_account_does_not_have_google_provider(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'status' => AccountStatusEnum::DEACTIVATED,
        ])->withoutPersisting()->create();

        $command = new GoogleOAuthLoginCommand(
            email: $account->email(),
            googleId: 'google-user-4',
            marketingConsent: false,
        );

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($command->email)
            ->willReturn($account);

        $this->accounts
            ->expects($this->never())
            ->method('save');

        $this->tokenGenerator
            ->expects($this->never())
            ->method('generate');

        // Act
        $exception = null;
        try {
            ($this->handler)($command);
        } catch (\Throwable $throwable) {
            $exception = $throwable;
        }

        // Assert
        Assert::assertInstanceOf(AccountNotActiveException::class, $exception);
        Assert::assertFalse($account->hasOAuthProvider(OAuthProvider::GOOGLE));
        Assert::assertTrue($account->status()->isDeactivated());
    }

    #[Test]
    public function throws_when_deactivated_account_already_has_google_provider(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'status' => AccountStatusEnum::DEACTIVATED,
        ])->withoutPersisting()->create();
        $account->addOAuthProvider(OAuthProvider::GOOGLE);

        $command = new GoogleOAuthLoginCommand(
            email: $account->email(),
            googleId: 'google-user-5',
            marketingConsent: false,
        );

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($command->email)
            ->willReturn($account);

        $this->accounts
            ->expects($this->never())
            ->method('save');

        $this->tokenGenerator
            ->expects($this->never())
            ->method('generate');

        // Act
        $exception = null;
        try {
            ($this->handler)($command);
        } catch (\Throwable $throwable) {
            $exception = $throwable;
        }

        // Assert
        Assert::assertInstanceOf(AccountNotActiveException::class, $exception);
        Assert::assertTrue($account->hasOAuthProvider(OAuthProvider::GOOGLE));
        Assert::assertTrue($account->status()->isDeactivated());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->accounts = $this->createMock(AccountRepositoryInterface::class);
        $this->tokenGenerator = $this->createMock(TokenGenerator::class);
        $logger = $this->createStub(LoggerInterface::class);

        $this->handler = new GoogleOAuthLoginHandler(
            $this->accounts,
            $this->tokenGenerator,
            new RefreshTokenGenerator(),
            new AccountMergingPolicy(),
            $logger,
        );
    }
}
