<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\OidcLogin;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Application\Command\OidcLogin\OidcLoginCommand;
use App\Identity\Account\Application\Command\OidcLogin\OidcLoginHandler;
use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Enum\OAuthProvider;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\AccountNotActiveException;
use App\Identity\Account\Domain\Account\Exception\AccountOAuthIdentityConflictException;
use App\Identity\Account\Domain\Account\Exception\SelfRegistrationNotAllowedException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Policy\AccountMergingPolicy;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Identity\RefreshToken\Infrastructure\Security\RefreshTokenGenerator;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(OidcLoginHandler::class)]
#[UsesClass(OidcLoginCommand::class)]
#[UsesClass(TokensDto::class)]
#[UsesClass(AccountMergingPolicy::class)]
#[UsesClass(RefreshTokenGenerator::class)]
#[UsesClass(SelfRegistrationNotAllowedException::class)]
#[UsesClass(AccountOAuthIdentityConflictException::class)]
final class OidcLoginHandlerTest extends TestCase
{
    use Factories;

    private AccountRepositoryInterface&MockObject $accounts;

    private TokenGenerator&MockObject $tokenGenerator;

    private LoggerInterface $logger;

    private OidcLoginHandler $handler;

    #[Test]
    public function creates_an_account_and_records_the_subject_when_neither_is_known(): void
    {
        // Arrange
        $command = new OidcLoginCommand(
            email: 'New.Manager@Example.com',
            subject: 'dex-subject-1',
            marketingConsent: true,
        );

        $savedAccount = null;

        $this->accounts
            ->expects($this->once())
            ->method('findByOAuthIdentity')
            ->with(OAuthProvider::OIDC, 'dex-subject-1')
            ->willReturn(null);

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->with($command->email)
            ->willReturn(null);

        $this->accounts
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (Account $account) use (&$savedAccount): void {
                $savedAccount = $account;

                Assert::assertSame('new.manager@example.com', $account->email());
                Assert::assertTrue($account->isActive());
                Assert::assertSame(RoleEnum::USER, $account->role());
                Assert::assertTrue($account->hasOAuthProvider(OAuthProvider::OIDC));
                Assert::assertTrue($account->hasOAuthIdentity(OAuthProvider::OIDC, 'dex-subject-1'));
                Assert::assertTrue($account->hasMarketingConsent());
            });

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
    public function finds_the_account_by_subject_even_when_the_email_has_changed_at_the_issuer(): void
    {
        // The whole reason the subject is stored: someone who changes their address at the
        // provider keeps their account, and the email is never consulted for them again.
        // Arrange
        $account = AccountFactory::new(['status' => AccountStatusEnum::ACTIVE])->withoutPersisting()->create();
        $account->linkOAuthIdentity(Id::generate(), OAuthProvider::OIDC, 'dex-subject-2');

        $command = new OidcLoginCommand(
            email: 'brand.new.address@example.com',
            subject: 'dex-subject-2',
        );

        $this->accounts
            ->expects($this->once())
            ->method('findByOAuthIdentity')
            ->with(OAuthProvider::OIDC, 'dex-subject-2')
            ->willReturn($account);

        $this->accounts
            ->expects($this->never())
            ->method('findByEmail');

        $this->tokenGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($this->identicalTo($account))
            ->willReturn('jwt-by-subject');

        // Act
        $result = ($this->handler)($command);

        // Assert
        Assert::assertSame('jwt-by-subject', $result->jwtToken);
        Assert::assertSame('brand.new.address@example.com', $command->email);
        Assert::assertNotSame($command->email, $account->email());
    }

    #[Test]
    public function links_the_subject_to_an_account_that_predates_sso_and_auto_verifies_it(): void
    {
        // Someone invited before SSO existed signs in for the first time: the email is what
        // matches them, and from then on the subject does.
        // Arrange
        $account = AccountFactory::new([
            'status' => AccountStatusEnum::PENDING_EMAIL_VERIFICATION,
        ])->withoutPersisting()->create();

        $command = new OidcLoginCommand(email: $account->email(), subject: 'dex-subject-3');

        $this->accounts
            ->expects($this->once())
            ->method('findByOAuthIdentity')
            ->willReturn(null);

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
            ->willReturn('jwt-linked-account');

        // Act
        $result = ($this->handler)($command);

        // Assert
        Assert::assertSame('jwt-linked-account', $result->jwtToken);
        Assert::assertTrue($account->hasOAuthProvider(OAuthProvider::OIDC));
        Assert::assertTrue($account->hasOAuthIdentity(OAuthProvider::OIDC, 'dex-subject-3'));
        Assert::assertTrue($account->status()->isActive());
    }

    #[Test]
    public function links_nothing_new_on_a_returning_sign_in(): void
    {
        // Arrange
        $account = AccountFactory::new(['status' => AccountStatusEnum::ACTIVE])->withoutPersisting()->create();
        $account->linkOAuthIdentity(Id::generate(), OAuthProvider::OIDC, 'dex-subject-4');

        $identityBefore = $account->oauthIdentityFor(OAuthProvider::OIDC);

        $command = new OidcLoginCommand(email: $account->email(), subject: 'dex-subject-4');

        $this->accounts
            ->expects($this->once())
            ->method('findByOAuthIdentity')
            ->willReturn($account);

        $this->tokenGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('jwt-returning');

        // Act
        $result = ($this->handler)($command);

        // Assert
        Assert::assertSame('jwt-returning', $result->jwtToken);
        Assert::assertSame($identityBefore, $account->oauthIdentityFor(OAuthProvider::OIDC));
    }

    #[Test]
    public function refuses_a_second_subject_for_an_account_already_linked_to_one(): void
    {
        // The issuer reporting a different `sub` for an address it already placed means the
        // instance was pointed somewhere new, or two people share an address upstream. Either
        // way, quietly attaching both to one account is the wrong answer.
        // Arrange
        $account = AccountFactory::new(['status' => AccountStatusEnum::ACTIVE])->withoutPersisting()->create();
        $account->linkOAuthIdentity(Id::generate(), OAuthProvider::OIDC, 'dex-subject-5');

        $command = new OidcLoginCommand(email: $account->email(), subject: 'a-different-subject');

        $this->accounts
            ->expects($this->once())
            ->method('findByOAuthIdentity')
            ->willReturn(null);

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->willReturn($account);

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
        Assert::assertInstanceOf(AccountOAuthIdentityConflictException::class, $exception);
    }

    #[Test]
    public function refuses_a_deactivated_account(): void
    {
        // Arrange
        $account = AccountFactory::new(['status' => AccountStatusEnum::DEACTIVATED])->withoutPersisting()->create();

        $command = new OidcLoginCommand(email: $account->email(), subject: 'dex-subject-6');

        $this->accounts
            ->expects($this->once())
            ->method('findByOAuthIdentity')
            ->willReturn(null);

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
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
        Assert::assertFalse($account->hasOAuthProvider(OAuthProvider::OIDC));
    }

    #[Test]
    public function refuses_a_deactivated_account_that_was_already_linked(): void
    {
        // Arrange
        $account = AccountFactory::new(['status' => AccountStatusEnum::DEACTIVATED])->withoutPersisting()->create();
        $account->linkOAuthIdentity(Id::generate(), OAuthProvider::OIDC, 'dex-subject-7');

        $command = new OidcLoginCommand(email: $account->email(), subject: 'dex-subject-7');

        $this->accounts
            ->expects($this->once())
            ->method('findByOAuthIdentity')
            ->willReturn($account);

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
    }

    #[Test]
    public function refuses_to_create_an_account_when_sign_up_is_closed(): void
    {
        // An SSO provider admitting someone says the address is real, not that the operator
        // of this instance invited them.
        // Arrange
        $command = new OidcLoginCommand(email: 'stranger@example.com', subject: 'dex-subject-8');

        $this->accounts
            ->expects($this->once())
            ->method('findByOAuthIdentity')
            ->willReturn(null);

        $this->accounts
            ->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);

        $this->accounts
            ->expects($this->never())
            ->method('save');

        $this->tokenGenerator
            ->expects($this->never())
            ->method('generate');

        // Act
        $exception = null;
        try {
            ($this->handlerWith(allowSignup: false))($command);
        } catch (\Throwable $throwable) {
            $exception = $throwable;
        }

        // Assert
        Assert::assertInstanceOf(SelfRegistrationNotAllowedException::class, $exception);
    }

    #[Test]
    public function signs_in_an_existing_account_when_sign_up_is_closed(): void
    {
        // Closing sign-up refuses new accounts, not the people who already have one.
        // Arrange
        $account = AccountFactory::new(['status' => AccountStatusEnum::ACTIVE])->withoutPersisting()->create();
        $account->linkOAuthIdentity(Id::generate(), OAuthProvider::OIDC, 'dex-subject-9');

        $command = new OidcLoginCommand(email: $account->email(), subject: 'dex-subject-9');

        $this->accounts
            ->expects($this->once())
            ->method('findByOAuthIdentity')
            ->willReturn($account);

        $this->tokenGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('jwt-closed-instance');

        // Act
        $result = ($this->handlerWith(allowSignup: false))($command);

        // Assert
        Assert::assertSame('jwt-closed-instance', $result->jwtToken);
        Assert::assertNotSame('', $result->refreshToken);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->accounts = $this->createMock(AccountRepositoryInterface::class);
        $this->tokenGenerator = $this->createMock(TokenGenerator::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->handler = $this->handlerWith(allowSignup: true);
    }

    private function handlerWith(bool $allowSignup): OidcLoginHandler
    {
        return new OidcLoginHandler(
            $this->accounts,
            $this->tokenGenerator,
            new RefreshTokenGenerator(),
            new AccountMergingPolicy(),
            $this->logger,
            $allowSignup,
        );
    }
}
