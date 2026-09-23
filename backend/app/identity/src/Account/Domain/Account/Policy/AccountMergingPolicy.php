<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Policy;

use App\Identity\Account\Domain\Account\Enum\OAuthProvider;
use App\Identity\Account\Domain\Account\Model\Account;

/**
 * Domain policy for merging OAuth accounts with existing accounts.
 *
 * This policy encapsulates the business logic for handling cases where
 * a user tries to login with OAuth (e.g., Google) but an account with
 * the same email already exists.
 */
final readonly class AccountMergingPolicy
{
    /**
     * Determines if an OAuth provider can be added to an existing account.
     *
     * Rules:
     * - Account must be active or pending email verification
     * - OAuth provider must not already be registered for this account
     * - Account must not be deactivated
     */
    public function canAddOAuthProvider(Account $account, OAuthProvider $provider): bool
    {
        // Cannot add provider to deactivated accounts
        if ($account->isDeleted()) {
            return false;
        }

        // Cannot add provider if it already exists
        return !$account->hasOAuthProvider($provider);
    }

    /**
     * Determines if an account should be automatically verified when OAuth is added.
     *
     * Rules:
     * - If account is pending email verification and OAuth provider has verified email (like Google),
     *   we can trust the verification and activate the account
     */
    public function shouldAutoVerifyOnOAuthMerge(Account $account, OAuthProvider $provider): bool
    {
        // Only auto-verify if account is pending verification
        if (!$account->status()->isPendingEmailVerification()) {
            return false;
        }

        // Only trusted OAuth providers can auto-verify
        return $this->isProviderTrusted($provider);
    }

    /**
     * Checks if OAuth provider is trusted for email verification.
     */
    private function isProviderTrusted(OAuthProvider $provider): bool
    {
        return match ($provider) {
            OAuthProvider::GOOGLE => true,
            OAuthProvider::LOCAL => false,
        };
    }
}
