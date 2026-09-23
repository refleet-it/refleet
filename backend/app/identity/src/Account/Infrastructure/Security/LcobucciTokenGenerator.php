<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Security;

use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Infrastructure\Service\TokenGenerator;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

final readonly class LcobucciTokenGenerator implements TokenGenerator
{
    private const string ISSUER = 'refleet';

    private const string TOKEN_TTL = '+1 hour';

    private Configuration $config;

    public function __construct()
    {
        $privateKey = $_ENV['JWT_PRIVATE_KEY'] ?? throw new \InvalidArgumentException('JWT_PRIVATE_KEY environment variable is not set');
        if (!\is_string($privateKey) || '' === $privateKey) {
            throw new \InvalidArgumentException('JWT_PRIVATE_KEY must be a non-empty string');
        }

        $publicKey = $_ENV['JWT_PUBLIC_KEY'] ?? throw new \InvalidArgumentException('JWT_PUBLIC_KEY environment variable is not set');
        if (!\is_string($publicKey) || '' === $publicKey) {
            throw new \InvalidArgumentException('JWT_PUBLIC_KEY must be a non-empty string');
        }

        $this->config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::base64Encoded($privateKey),
            InMemory::base64Encoded($publicKey),
        );
    }

    #[\Override]
    public function generate(Account $account, ?AccountId $impersonatorId = null): string
    {
        $now = new \DateTimeImmutable();
        $accountId = $account->id()->asString();

        if ('' === $accountId) {
            throw new \InvalidArgumentException('Account ID cannot be empty');
        }

        $tokenBuilder = $this->config->builder()
            ->issuedBy(self::ISSUER)
            ->issuedAt($now)
            ->expiresAt($now->modify(self::TOKEN_TTL))
            ->relatedTo($accountId)
            ->withClaim('email', $account->email())
            ->withClaim('role', $account->role()->toSymfonyRole())
            ->withClaim('active', $account->isActive());

        if (null !== $impersonatorId) {
            $tokenBuilder = $tokenBuilder->withClaim('impersonatorId', $impersonatorId->asString());
        }

        $token = $tokenBuilder->getToken($this->config->signer(), $this->config->signingKey());

        return $token->toString();
    }
}
