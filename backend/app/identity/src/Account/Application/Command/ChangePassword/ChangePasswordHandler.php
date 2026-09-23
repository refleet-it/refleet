<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\ChangePassword;

use App\Identity\Account\Domain\Account\Exception\AccountNotFoundException;
use App\Identity\Account\Domain\Account\Exception\InvalidCurrentPasswordException;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Infrastructure\Service\PasswordHasher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangePasswordHandler
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository,
        private PasswordHasher $hasher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws AccountNotFoundException
     * @throws InvalidCurrentPasswordException
     */
    public function __invoke(ChangePasswordCommand $command): void
    {
        $account = $this->accountRepository->findById(AccountId::fromString($command->accountId));

        if (null === $account) {
            throw new AccountNotFoundException();
        }

        if (!$this->hasher->verify($command->currentPassword, $account->passwordHash())) {
            $this->logger->info('Password change failed: current password incorrect', ['accountId' => $command->accountId]);

            throw new InvalidCurrentPasswordException();
        }

        $account->changePassword($this->hasher->hash($command->newPassword));
        $this->accountRepository->update($account);

        $this->logger->info('Password changed successfully', ['accountId' => $command->accountId]);
    }
}
