<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\ResetPassword;

use App\Identity\Account\Domain\Account\Exception\PasswordResetTokenAlreadyUsedException;
use App\Identity\Account\Domain\Account\Exception\PasswordResetTokenExpiredException;
use App\Identity\Account\Domain\Account\Exception\PasswordResetTokenNotFoundException;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\Repository\PasswordResetTokenRepositoryInterface;
use App\Identity\Account\Infrastructure\Service\PasswordHasher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ResetPasswordHandler
{
    public function __construct(
        private PasswordResetTokenRepositoryInterface $tokenRepository,
        private AccountRepositoryInterface $accountRepository,
        private PasswordHasher $hasher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws PasswordResetTokenNotFoundException
     * @throws PasswordResetTokenExpiredException
     * @throws PasswordResetTokenAlreadyUsedException
     */
    public function __invoke(ResetPasswordCommand $command): void
    {
        $this->logger->info('Processing password reset request');

        $resetToken = $this->tokenRepository->findByToken($command->token);

        if (null === $resetToken) {
            $this->logger->warning('Password reset failed: token not found');

            throw new PasswordResetTokenNotFoundException();
        }

        if ($resetToken->isExpired()) {
            $this->logger->warning('Password reset failed: token expired');

            throw new PasswordResetTokenExpiredException();
        }

        if ($resetToken->isUsed()) {
            $this->logger->warning('Password reset failed: token already used');

            throw new PasswordResetTokenAlreadyUsedException();
        }

        $account = $resetToken->account();
        $newPasswordHash = $this->hasher->hash($command->newPassword);

        $account->resetPassword($newPasswordHash);
        $resetToken->markAsUsed();

        $this->accountRepository->update($account);
        $this->tokenRepository->save($resetToken);

        $this->logger->info('Password reset completed for account: '.$account->email());
    }
}
