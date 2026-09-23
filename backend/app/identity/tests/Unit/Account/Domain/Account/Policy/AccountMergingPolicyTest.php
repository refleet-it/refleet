<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Domain\Account\Policy;

use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Enum\OAuthProvider;
use App\Identity\Account\Domain\Account\Policy\AccountMergingPolicy;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(AccountMergingPolicy::class)]
final class AccountMergingPolicyTest extends TestCase
{
    use Factories;

    private AccountMergingPolicy $policy;

    #[Test]
    public function can_add_oauth_provider_returns_false_for_deactivated_account(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'status' => AccountStatusEnum::DEACTIVATED,
        ])->withoutPersisting()->create();

        // Act
        $result = $this->policy->canAddOAuthProvider($account, OAuthProvider::GOOGLE);

        // Assert
        Assert::assertFalse($result);
    }

    #[Test]
    public function can_add_oauth_provider_returns_false_when_provider_already_exists(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $account->addOAuthProvider(OAuthProvider::GOOGLE);

        // Act
        $result = $this->policy->canAddOAuthProvider($account, OAuthProvider::GOOGLE);

        // Assert
        Assert::assertFalse($result);
    }

    #[Test]
    public function can_add_oauth_provider_returns_true_for_active_account_without_provider(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'status' => AccountStatusEnum::ACTIVE,
        ])->withoutPersisting()->create();

        // Act
        $result = $this->policy->canAddOAuthProvider($account, OAuthProvider::GOOGLE);

        // Assert
        Assert::assertTrue($result);
    }

    #[Test]
    public function can_add_oauth_provider_returns_true_for_suspended_account_without_provider(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'status' => AccountStatusEnum::SUSPENDED,
        ])->withoutPersisting()->create();

        // Act
        $result = $this->policy->canAddOAuthProvider($account, OAuthProvider::GOOGLE);

        // Assert
        Assert::assertTrue($result);
    }

    #[Test]
    public function should_auto_verify_on_oauth_merge_returns_true_for_pending_email_verification_with_google(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'status' => AccountStatusEnum::PENDING_EMAIL_VERIFICATION,
        ])->withoutPersisting()->create();

        // Act
        $result = $this->policy->shouldAutoVerifyOnOAuthMerge($account, OAuthProvider::GOOGLE);

        // Assert
        Assert::assertTrue($result);
    }

    #[Test]
    public function should_auto_verify_on_oauth_merge_returns_false_for_pending_email_verification_with_local(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'status' => AccountStatusEnum::PENDING_EMAIL_VERIFICATION,
        ])->withoutPersisting()->create();

        // Act
        $result = $this->policy->shouldAutoVerifyOnOAuthMerge($account, OAuthProvider::LOCAL);

        // Assert
        Assert::assertFalse($result);
    }

    #[Test]
    public function should_auto_verify_on_oauth_merge_returns_false_for_non_pending_status_even_with_google(): void
    {
        // Arrange
        $account = AccountFactory::new([
            'status' => AccountStatusEnum::ACTIVE,
        ])->withoutPersisting()->create();

        // Act
        $result = $this->policy->shouldAutoVerifyOnOAuthMerge($account, OAuthProvider::GOOGLE);

        // Assert
        Assert::assertFalse($result);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->policy = new AccountMergingPolicy();
    }
}
