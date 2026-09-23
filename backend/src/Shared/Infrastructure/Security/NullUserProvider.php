<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Domain\User\AccountUser;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Registered as `app_user_provider` in every context but Identity, which is the only one
 * with a real, database-backed one (see AccountUserProvider). Both JwtAuthenticator and
 * ApiKeyAuthenticator build their AccountUser directly and pass it to UserBadge as an
 * explicit loader, which makes Symfony's security system skip the firewall's configured
 * provider entirely — so this exists only to satisfy the framework's schema requirement
 * that a firewall have one, and should never actually be called. If it ever is, that is a
 * bug: fail loudly rather than silently return a stand-in user.
 *
 * @template-implements UserProviderInterface<AccountUser>
 */
final class NullUserProvider implements UserProviderInterface
{
    #[\Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        throw new UnsupportedUserException('NullUserProvider should never be called: authenticators supply their own user loader.');
    }

    #[\Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new UserNotFoundException('NullUserProvider should never be called: authenticators supply their own user loader.');
    }

    #[\Override]
    public function supportsClass(string $class): bool
    {
        return AccountUser::class === $class || \is_subclass_of($class, AccountUser::class);
    }
}
