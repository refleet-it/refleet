<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\OidcLogin;

use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\Enum\OAuthProvider;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\AccountNotActiveException;
use App\Identity\Account\Domain\Account\Exception\AccountOAuthIdentityConflictException;
use App\Identity\Account\Domain\Account\Exception\SelfRegistrationNotAllowedException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Policy\AccountMergingPolicy;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Identity\RefreshToken\Infrastructure\Security\RefreshTokenGenerator;
use App\Shared\Domain\ValueObject\Id;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class OidcLoginHandler
{
    private const string REFRESH_TOKEN_EXPIRY = '+1 week';

    public function __construct(
        private AccountRepositoryInterface $repository,
        private TokenGenerator $tokenGenerator,
        private RefreshTokenGenerator $refreshTokenGenerator,
        private AccountMergingPolicy $mergingPolicy,
        private LoggerInterface $logger,
        // Same reasoning as the Google path: a provider vouching for an address says the
        // address is real, not that its owner was invited to this instance.
        #[Autowire('%env(bool:ALLOW_SIGNUP)%')]
        private bool $allowSignup,
    ) {
    }

    /**
     * @throws AccountNotActiveException
     * @throws SelfRegistrationNotAllowedException
     * @throws AccountOAuthIdentityConflictException
     */
    public function __invoke(OidcLoginCommand $command): TokensDto
    {
        $account = $this->findAccount($command);

        if (null === $account) {
            $account = $this->createAccount($command);
        } else {
            $this->linkIdentity($account, $command);
        }

        if (!$account->isActive()) {
            $this->logger->warning('Attempted sign-in to an inactive account through OIDC: '.$command->email);

            throw new AccountNotActiveException();
        }

        $generatedRefreshToken = $this->refreshTokenGenerator->generate();
        $account->generateRefreshToken($generatedRefreshToken->hashedToken, new \DateTimeImmutable(self::REFRESH_TOKEN_EXPIRY));

        $this->logger->info('Signed in through OIDC: '.$command->email);

        return new TokensDto(
            jwtToken: $this->tokenGenerator->generate($account),
            refreshToken: $generatedRefreshToken->plainToken,
        );
    }

    /**
     * The subject comes first and the address second, which is the whole point of storing the
     * subject. Someone who changes their email address at the issuer keeps their account; an
     * address that changes hands at the issuer does not carry the previous owner's account
     * with it. The email is only how an account that predates SSO — or one created by
     * invitation — gets picked up on its owner's first single sign-in.
     */
    private function findAccount(OidcLoginCommand $command): ?Account
    {
        $account = $this->repository->findByOAuthIdentity(OAuthProvider::OIDC, $command->subject);

        if (null !== $account) {
            return $account;
        }

        return $this->repository->findByEmail($command->email);
    }

    private function createAccount(OidcLoginCommand $command): Account
    {
        if (!$this->allowSignup) {
            $this->logger->warning('Refused to create an account from OIDC, sign-up is closed on this instance: '.$command->email);

            throw new SelfRegistrationNotAllowedException();
        }

        $this->logger->info('Creating a new account from OIDC: '.$command->email);

        $account = Account::createFromOAuth(
            id: AccountId::generate(),
            email: Email::fromString($command->email),
            provider: OAuthProvider::OIDC,
            role: RoleEnum::USER,
            marketingConsent: $command->marketingConsent,
        );

        $account->linkOAuthIdentity(Id::generate(), OAuthProvider::OIDC, $command->subject);

        $this->repository->save($account);

        return $account;
    }

    private function linkIdentity(Account $account, OidcLoginCommand $command): void
    {
        // A returning sign-in has nothing to merge, so it needs no permission to. Only the
        // first one does, and the policy is what refuses it for a deactivated account.
        if (!$account->hasOAuthProvider(OAuthProvider::OIDC)
            && !$this->mergingPolicy->canAddOAuthProvider($account, OAuthProvider::OIDC)) {
            $this->logger->warning(
                'Cannot attach the OIDC provider to account: '.$command->email
                .' (account status: '.$account->status()->value.')'
            );

            throw new AccountNotActiveException();
        }

        // Idempotent on a returning sign-in, and a 409 when the issuer reports a different
        // subject for an account it already has one for — see Account::linkOAuthIdentity().
        $account->linkOAuthIdentity(Id::generate(), OAuthProvider::OIDC, $command->subject);

        if ($this->mergingPolicy->shouldAutoVerifyOnOAuthMerge($account, OAuthProvider::OIDC)) {
            $this->logger->info('Auto-verifying the email address on OIDC sign-in: '.$command->email);
            $account->verifyEmail();
        }

        $this->repository->save($account);
    }
}
