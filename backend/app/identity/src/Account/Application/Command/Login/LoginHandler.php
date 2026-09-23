<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\Login;

use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\Exception\AccountNotActiveException;
use App\Identity\Account\Domain\Account\Exception\EmailNotVerifiedException;
use App\Identity\Account\Domain\Account\Exception\InvalidCredentialsException;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Infrastructure\Service\PasswordHasher;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use App\Identity\RefreshToken\Infrastructure\Security\RefreshTokenGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class LoginHandler
{
    public function __construct(
        private AccountRepositoryInterface $repository,
        private PasswordHasher $hasher,
        private TokenGenerator $tokenGenerator,
        private RefreshTokenGenerator $refreshTokenGenerator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws InvalidCredentialsException
     * @throws AccountNotActiveException
     * @throws EmailNotVerifiedException
     */
    public function __invoke(LoginCommand $command): TokensDto
    {
        $this->logger->info('Processing login request', ['email' => $command->email]);

        $account = $this->repository->findByEmail($command->email);

        if (null === $account) {
            $this->logger->warning('Login failed: account not found', ['email' => $command->email]);

            throw new InvalidCredentialsException();
        }

        if (!$this->hasher->verify($command->plainPassword, $account->passwordHash())) {
            $this->logger->warning('Login failed: invalid password', ['email' => $command->email]);

            throw new InvalidCredentialsException();
        }

        if ($account->status()->isPendingEmailVerification()) {
            $this->logger->warning('Login failed: email not verified', ['email' => $command->email]);

            throw new EmailNotVerifiedException();
        }

        if (!$account->isActive()) {
            $this->logger->warning('Login failed: account not active', [
                'email' => $command->email,
                'status' => $account->status()->value,
            ]);

            throw new AccountNotActiveException();
        }

        $generatedRefreshToken = $this->refreshTokenGenerator->generate();
        $account->generateRefreshToken($generatedRefreshToken->hashedToken, new \DateTimeImmutable('+1 week'));

        $this->logger->info('Login successful', ['email' => $command->email, 'accountId' => $account->id()->asString()]);

        return new TokensDto(
            jwtToken: $this->tokenGenerator->generate($account),
            refreshToken: $generatedRefreshToken->plainToken,
        );
    }
}
