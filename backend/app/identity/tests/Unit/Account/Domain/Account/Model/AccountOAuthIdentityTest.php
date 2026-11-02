<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Model;

use App\Identity\Account\Domain\Account\Enum\OAuthProvider;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Model\AccountOAuthIdentity;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountOAuthIdentity::class)]
final class AccountOAuthIdentityTest extends TestCase
{
    #[Test]
    public function links_an_account_to_a_provider_subject(): void
    {
        $id = Id::fromString('550e8400-e29b-41d4-a716-446655440001');
        $account = $this->createAccount();

        $identity = AccountOAuthIdentity::link($id, $account, OAuthProvider::OIDC, 'the-subject');

        Assert::assertSame($id->asString(), $identity->id()->asString());
        Assert::assertSame($account, $identity->account());
        Assert::assertSame(OAuthProvider::OIDC, $identity->provider());
        Assert::assertSame('the-subject', $identity->subject());
    }

    #[Test]
    public function matches_only_the_same_provider_and_subject(): void
    {
        $identity = AccountOAuthIdentity::link(
            Id::fromString('550e8400-e29b-41d4-a716-446655440001'),
            $this->createAccount(),
            OAuthProvider::OIDC,
            'the-subject',
        );

        Assert::assertTrue($identity->matches(OAuthProvider::OIDC, 'the-subject'));
        Assert::assertFalse($identity->matches(OAuthProvider::GOOGLE, 'the-subject'));
        Assert::assertFalse($identity->matches(OAuthProvider::OIDC, 'another-subject'));
    }

    #[Test]
    public function records_when_the_link_was_made(): void
    {
        $before = new \DateTimeImmutable();

        $identity = AccountOAuthIdentity::link(
            Id::fromString('550e8400-e29b-41d4-a716-446655440001'),
            $this->createAccount(),
            OAuthProvider::OIDC,
            'the-subject',
        );

        Assert::assertGreaterThanOrEqual($before->getTimestamp(), $identity->createdAt()->getTimestamp());
    }

    private function createAccount(): Account
    {
        return Account::create(
            AccountId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            Email::fromString('test@example.com'),
            HashedPassword::fromString('hashed_password_123'),
            RoleEnum::USER,
        );
    }
}
