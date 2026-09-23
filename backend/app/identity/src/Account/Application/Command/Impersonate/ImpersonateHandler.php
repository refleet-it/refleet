<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\Impersonate;

use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\Exception\AccountNotFoundException;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Identity\RefreshToken\Infrastructure\Security\RefreshTokenGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ImpersonateHandler
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository,
        private TokenGenerator $tokenGenerator,
        private RefreshTokenGenerator $refreshTokenGenerator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws AccountNotFoundException
     */
    public function __invoke(ImpersonateCommand $command): TokensDto
    {
        $this->logger->info('Admin requesting impersonation data', [
            'adminId' => $command->adminAccountId->asString(),
            'targetId' => $command->targetAccountId->asString(),
        ]);

        $targetAccount = $this->accountRepository->findById($command->targetAccountId);
        if (null === $targetAccount) {
            $this->logger->info('Target account not found for impersonation', [
                'targetId' => $command->targetAccountId->asString(),
                'adminId' => $command->adminAccountId->asString(),
            ]);
            throw new AccountNotFoundException();
        }

        if (!$targetAccount->isActive()) {
            $this->logger->info('Target account is not active for impersonation', [
                'targetId' => $command->targetAccountId->asString(),
                'adminId' => $command->adminAccountId->asString(),
            ]);
            throw new AccountNotFoundException();
        }

        $token = $this->tokenGenerator->generate($targetAccount, $command->adminAccountId);

        $this->logger->info('Impersonation data provided successfully', [
            'adminId' => $command->adminAccountId->asString(),
            'targetId' => $targetAccount->id()->asString(),
            'targetEmail' => $targetAccount->email(),
        ]);

        $generatedRefreshToken = $this->refreshTokenGenerator->generate();
        $targetAccount->generateRefreshToken(
            $generatedRefreshToken->hashedToken,
            new \DateTimeImmutable('+1 week'),
            $command->adminAccountId->asString(),
        );

        return new TokensDto(
            jwtToken: $token,
            refreshToken: $generatedRefreshToken->plainToken,
        );
    }
}
