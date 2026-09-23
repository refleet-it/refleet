<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\GoogleOAuthLogin;

use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\Enum\OAuthProvider;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\AccountNotActiveException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Policy\AccountMergingPolicy;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Identity\RefreshToken\Infrastructure\Security\RefreshTokenGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GoogleOAuthLoginHandler
{
    private const string REFRESH_TOKEN_EXPIRY = '+1 week';

    public function __construct(
        private AccountRepositoryInterface $repository,
        private TokenGenerator $tokenGenerator,
        private RefreshTokenGenerator $refreshTokenGenerator,
        private AccountMergingPolicy $mergingPolicy,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws AccountNotActiveException
     */
    public function __invoke(GoogleOAuthLoginCommand $command): TokensDto
    {
        $this->logger->info('Processing Google OAuth login for email: '.$command->email);

        $account = $this->repository->findByEmail($command->email);

        if (null === $account) {
            $this->logger->info('Creating new account from Google OAuth: '.$command->email);
            $account = $this->createAccountFromGoogle($command);
            $this->repository->save($account);
        } else {
            $this->logger->info('Account exists, attempting to merge Google OAuth provider: '.$command->email);
            $account = $this->mergeOAuthProvider($account, $command);
        }

        if (!$account->isActive()) {
            $this->logger->warning('Attempted login to inactive account via Google OAuth: '.$command->email);

            throw new AccountNotActiveException();
        }

        $generatedRefreshToken = $this->refreshTokenGenerator->generate();
        $account->generateRefreshToken($generatedRefreshToken->hashedToken, new \DateTimeImmutable(self::REFRESH_TOKEN_EXPIRY));

        $this->logger->info('Successfully logged in via Google OAuth: '.$command->email);

        return new TokensDto(
            jwtToken: $this->tokenGenerator->generate($account),
            refreshToken: $generatedRefreshToken->plainToken,
        );
    }

    private function createAccountFromGoogle(GoogleOAuthLoginCommand $command): Account
    {
        return Account::createFromOAuth(
            id: AccountId::generate(),
            email: Email::fromString($command->email),
            provider: OAuthProvider::GOOGLE,
            role: RoleEnum::USER,
            marketingConsent: $command->marketingConsent,
        );
    }

    private function mergeOAuthProvider(Account $account, GoogleOAuthLoginCommand $command): Account
    {
        if (!$this->mergingPolicy->canAddOAuthProvider($account, OAuthProvider::GOOGLE)) {
            $this->logger->warning(
                'Cannot merge Google OAuth provider to account: '.$command->email
                .' (account status: '.$account->status()->value.')'
            );

            // If account already has Google provider, we can still proceed with login
            if ($account->hasOAuthProvider(OAuthProvider::GOOGLE)) {
                return $account;
            }

            throw new AccountNotActiveException();
        }

        $account->addOAuthProvider(OAuthProvider::GOOGLE);

        if ($this->mergingPolicy->shouldAutoVerifyOnOAuthMerge($account, OAuthProvider::GOOGLE)) {
            $this->logger->info('Auto-verifying account via Google OAuth: '.$command->email);
            $account->verifyEmail();
        }

        $this->repository->save($account);

        return $account;
    }
}
