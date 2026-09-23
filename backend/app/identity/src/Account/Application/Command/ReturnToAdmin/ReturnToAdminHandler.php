<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\ReturnToAdmin;

use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\Exception\AccountNotFoundException;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Identity\RefreshToken\Infrastructure\Security\RefreshTokenGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReturnToAdminHandler
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
    public function __invoke(ReturnToAdminCommand $command): TokensDto
    {
        $adminAccount = $this->accountRepository->findById($command->adminAccountId);

        if (null === $adminAccount) {
            $this->logger->warning('Return to admin failed: admin account not found', [
                'adminId' => $command->adminAccountId->asString(),
            ]);

            throw new AccountNotFoundException();
        }

        $generatedRefreshToken = $this->refreshTokenGenerator->generate();
        $adminAccount->generateRefreshToken($generatedRefreshToken->hashedToken, new \DateTimeImmutable('+1 week'));

        $this->logger->info('Returned to admin successfully', ['adminId' => $adminAccount->id()->asString()]);

        return new TokensDto(
            jwtToken: $this->tokenGenerator->generate($adminAccount),
            refreshToken: $generatedRefreshToken->plainToken,
        );
    }
}
