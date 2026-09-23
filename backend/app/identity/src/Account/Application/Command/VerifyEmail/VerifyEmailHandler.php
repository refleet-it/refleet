<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\VerifyEmail;

use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\Exception\EmailVerificationTokenAlreadyUsedException;
use App\Identity\Account\Domain\Account\Exception\EmailVerificationTokenExpiredException;
use App\Identity\Account\Domain\Account\Exception\EmailVerificationTokenNotFoundException;
use App\Identity\Account\Domain\Account\Model\EmailVerificationToken;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\Repository\EmailVerificationTokenRepositoryInterface;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Identity\RefreshToken\Infrastructure\Security\RefreshTokenGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class VerifyEmailHandler
{
    public function __construct(
        private EmailVerificationTokenRepositoryInterface $tokenRepository,
        private AccountRepositoryInterface $accountRepository,
        private TokenGenerator $tokenGenerator,
        private RefreshTokenGenerator $refreshTokenGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(VerifyEmailCommand $command): TokensDto
    {
        $this->logger->info('Processing email verification');

        $verificationToken = $this->tokenRepository->findByToken($command->token);

        if (null === $verificationToken) {
            $this->logger->warning('Email verification failed: token not found');

            throw new EmailVerificationTokenNotFoundException();
        }

        $this->assertTokenIsUsable($verificationToken);

        $account = $verificationToken->account();

        $account->verifyEmail();

        $verificationToken->markAsUsed();

        $this->accountRepository->save($account);

        $generatedRefreshToken = $this->refreshTokenGenerator->generate();
        $account->generateRefreshToken($generatedRefreshToken->hashedToken, new \DateTimeImmutable('+1 week'));

        $this->logger->info('Email verified successfully', [
            'accountId' => $account->id()->asString(),
            'email' => $account->email(),
        ]);

        return new TokensDto(
            jwtToken: $this->tokenGenerator->generate($account),
            refreshToken: $generatedRefreshToken->plainToken,
        );
    }

    /**
     * @throws EmailVerificationTokenAlreadyUsedException
     * @throws EmailVerificationTokenExpiredException
     */
    private function assertTokenIsUsable(EmailVerificationToken $verificationToken): void
    {
        if ($verificationToken->isUsed()) {
            $this->logger->warning('Email verification failed: token already used', [
                'tokenId' => $verificationToken->id()->asString(),
            ]);

            throw new EmailVerificationTokenAlreadyUsedException();
        }

        if ($verificationToken->isExpired()) {
            $this->logger->warning('Email verification failed: token expired', [
                'tokenId' => $verificationToken->id()->asString(),
                'expiresAt' => $verificationToken->expiresAt()->format('Y-m-d H:i:s'),
            ]);

            throw new EmailVerificationTokenExpiredException();
        }
    }
}
