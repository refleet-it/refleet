<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Security;

use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Infrastructure\Factory\AccountUserFactory;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\User\AuthenticatedUser;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @template-implements UserProviderInterface<AccountUser>
 */
final readonly class AccountUserProvider implements UserProviderInterface
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository,
        private AccountUserFactory $accountUserFactory,
    ) {
    }

    #[\Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof AccountUser) {
            throw new \InvalidArgumentException('User must be an instance of AccountUser');
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    #[\Override]
    public function loadUserByIdentifier(string $identifier): AuthenticatedUser
    {
        $account = $this->accountRepository->findByEmail($identifier);

        if (null === $account) {
            throw new UserNotFoundException(\sprintf('User with email "%s" not found.', $identifier));
        }

        return $this->accountUserFactory->createFromAccount($account);
    }

    #[\Override]
    public function supportsClass(string $class): bool
    {
        return AccountUser::class === $class || \is_subclass_of($class, AccountUser::class);
    }
}
