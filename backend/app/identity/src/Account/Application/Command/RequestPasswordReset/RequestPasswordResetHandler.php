<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\RequestPasswordReset;

use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RequestPasswordResetHandler
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RequestPasswordResetCommand $command): void
    {
        $account = $this->accountRepository->findByEmail($command->email);

        if (null === $account) {
            // Don't reveal if the account exists or not for security reasons
            $this->logger->info('Password reset requested for non-existent email: '.$command->email);

            return;
        }

        if ($account->isDeleted()) {
            $this->logger->info('Password reset requested for deleted account: '.$command->email);

            return;
        }

        $account->requestPasswordReset();
        $this->accountRepository->update($account);

        $this->logger->info('Password reset token generated for account: '.$command->email);
    }
}
