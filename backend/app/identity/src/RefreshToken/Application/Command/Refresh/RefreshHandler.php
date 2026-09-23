<?php

declare(strict_types=1);

namespace App\Identity\RefreshToken\Application\Command\Refresh;

use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Identity\RefreshToken\Domain\RefreshToken\Exception\RefreshTokenAlreadyUsedException;
use App\Identity\RefreshToken\Domain\RefreshToken\Exception\RefreshTokenExpiredException;
use App\Identity\RefreshToken\Domain\RefreshToken\Exception\RefreshTokenNotFoundException;
use App\Identity\RefreshToken\Domain\RefreshToken\Repository\RefreshTokenRepositoryInterface;
use App\Identity\RefreshToken\Infrastructure\Security\RefreshTokenGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RefreshHandler
{
    public function __construct(
        private RefreshTokenRepositoryInterface $repository,
        private TokenGenerator $tokenGenerator,
        private RefreshTokenGenerator $refreshTokenGenerator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws RefreshTokenNotFoundException
     * @throws RefreshTokenExpiredException
     * @throws RefreshTokenAlreadyUsedException
     */
    public function __invoke(RefreshCommand $command): TokensDto
    {
        $this->logger->info('Processing token refresh request');

        $refreshToken = $this->repository->findByToken($command->refreshToken);

        if (null === $refreshToken) {
            $this->logger->warning('Token refresh failed: token not found');

            throw new RefreshTokenNotFoundException();
        }

        if ($refreshToken->isExpired()) {
            $this->logger->warning('Token refresh failed: token expired');

            throw new RefreshTokenExpiredException();
        }

        if ($refreshToken->isRevoked()) {
            $this->logger->warning('Token refresh failed: token already used');

            throw new RefreshTokenAlreadyUsedException();
        }

        $account = $refreshToken->account();
        $impersonatorId = $refreshToken->impersonatorId();
        $refreshToken->revoke();
        $generatedRefreshToken = $this->refreshTokenGenerator->generate();
        $account->generateRefreshToken($generatedRefreshToken->hashedToken, new \DateTimeImmutable('+1 week'), $impersonatorId?->asString());

        $this->logger->info('Token refresh successful', ['accountId' => $account->id()->asString()]);

        return new TokensDto(
            jwtToken: $this->tokenGenerator->generate($account, $impersonatorId),
            refreshToken: $generatedRefreshToken->plainToken,
        );
    }
}
