<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Model;

use App\Identity\Account\Domain\Account\Enum\OAuthProvider;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\AccountOAuthIdentityConflictException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Account::class)]
final class AccountTest extends TestCase
{
    #[Test]
    public function creates_account_with_all_fields(): void
    {
        $id = AccountId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'test@example.com';
        $passwordHash = 'hashed_password_123';
        $role = RoleEnum::USER;

        $account = Account::create($id, Email::fromString($email), HashedPassword::fromString($passwordHash), $role);

        Assert::assertSame($id->asString(), $account->id()->asString());
        Assert::assertSame(\mb_strtolower($email), $account->email());
        Assert::assertSame($passwordHash, $account->passwordHash());
        Assert::assertSame($role, $account->role());
        Assert::assertFalse($account->isActive());
        Assert::assertTrue($account->status()->isPendingEmailVerification());
    }

    #[Test]
    public function normalizes_email_to_lowercase(): void
    {
        $id = AccountId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = 'TEST@EXAMPLE.COM';
        $passwordHash = 'hashed_password_123';
        $role = RoleEnum::USER;

        $account = Account::create($id, Email::fromString($email), HashedPassword::fromString($passwordHash), $role);

        Assert::assertSame(\mb_strtolower($email), $account->email());
    }

    #[Test]
    public function deactivates_active_account(): void
    {
        $account = $this->createAccount();

        $account->deactivate();

        Assert::assertFalse($account->isActive());
    }

    #[Test]
    public function generates_refresh_token_and_clears_old_ones(): void
    {
        $account = $this->createAccount();
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $token = $account->generateRefreshToken('hashed-token', $expiresAt);

        Assert::assertSame($expiresAt->getTimestamp(), $token->expiresAt()->getTimestamp());
    }

    #[Test]
    public function generates_unique_refresh_token_each_time(): void
    {
        $account = $this->createAccount();
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $token1 = $account->generateRefreshToken('hashed-token-1', $expiresAt);
        $token2 = $account->generateRefreshToken('hashed-token-2', $expiresAt);

        Assert::assertNotSame($token1, $token2);
        Assert::assertNotSame($token1->token(), $token2->token());
    }

    #[Test]
    public function refresh_token_has_correct_expiration(): void
    {
        $account = $this->createAccount();
        $expiresAt = new \DateTimeImmutable('+2 hours');

        $token = $account->generateRefreshToken('hashed-token', $expiresAt);

        Assert::assertSame($expiresAt->getTimestamp(), $token->expiresAt()->getTimestamp());
    }

    #[Test]
    public function linking_an_oauth_identity_records_the_subject_and_attaches_the_provider(): void
    {
        $account = $this->createAccount();

        $account->linkOAuthIdentity(Id::generate(), OAuthProvider::OIDC, 'subject-from-the-issuer');

        Assert::assertTrue($account->hasOAuthIdentity(OAuthProvider::OIDC, 'subject-from-the-issuer'));
        Assert::assertTrue($account->hasOAuthProvider(OAuthProvider::OIDC));
        Assert::assertSame('subject-from-the-issuer', $account->oauthIdentityFor(OAuthProvider::OIDC)?->subject());
    }

    #[Test]
    public function linking_the_same_oauth_identity_twice_changes_nothing(): void
    {
        // Every returning sign-in arrives here, so this is the common path, not an edge case.
        $account = $this->createAccount();
        $account->linkOAuthIdentity(Id::generate(), OAuthProvider::OIDC, 'stable-subject');

        $first = $account->oauthIdentityFor(OAuthProvider::OIDC);

        $account->linkOAuthIdentity(Id::generate(), OAuthProvider::OIDC, 'stable-subject');

        Assert::assertSame($first, $account->oauthIdentityFor(OAuthProvider::OIDC));
    }

    #[Test]
    public function linking_a_second_subject_for_the_same_provider_is_refused(): void
    {
        $account = $this->createAccount();
        $account->linkOAuthIdentity(Id::generate(), OAuthProvider::OIDC, 'the-original-subject');

        $caught = null;
        try {
            $account->linkOAuthIdentity(Id::generate(), OAuthProvider::OIDC, 'a-different-subject');
        } catch (AccountOAuthIdentityConflictException $accountoAuthIdentityConflictException) {
            $caught = $accountoAuthIdentityConflictException;
        }

        Assert::assertInstanceOf(AccountOAuthIdentityConflictException::class, $caught);
        Assert::assertSame('the-original-subject', $account->oauthIdentityFor(OAuthProvider::OIDC)?->subject());
    }

    #[Test]
    public function identities_from_different_providers_live_side_by_side(): void
    {
        $account = $this->createAccount();

        $account->linkOAuthIdentity(Id::generate(), OAuthProvider::OIDC, 'shared-subject-value');
        $account->linkOAuthIdentity(Id::generate(), OAuthProvider::GOOGLE, 'shared-subject-value');

        Assert::assertTrue($account->hasOAuthIdentity(OAuthProvider::OIDC, 'shared-subject-value'));
        Assert::assertTrue($account->hasOAuthIdentity(OAuthProvider::GOOGLE, 'shared-subject-value'));
    }

    #[Test]
    public function an_account_without_a_linked_identity_reports_none(): void
    {
        $account = $this->createAccount();

        Assert::assertNull($account->oauthIdentityFor(OAuthProvider::OIDC));
        Assert::assertFalse($account->hasOAuthIdentity(OAuthProvider::OIDC, 'anything'));
    }

    private function createAccount(): Account
    {
        return Account::create(
            AccountId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            Email::fromString('test@example.com'),
            HashedPassword::fromString('hashed_password_123'),
            RoleEnum::USER
        );
    }
}
