<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Security;

use App\Identity\Account\Domain\ApiKey\Repository\ApiKeyRepositoryInterface;
use App\Shared\Domain\Service\ApiKeyValidatorInterface;
use App\Shared\Domain\ValueObject\ValidatedApiKey;

final readonly class DoctrineApiKeyValidator implements ApiKeyValidatorInterface
{
    public function __construct(
        private ApiKeyRepositoryInterface $apiKeyRepository,
    ) {
    }

    #[\Override]
    public function validate(string $plainToken): ?ValidatedApiKey
    {
        $apiKey = $this->apiKeyRepository->findByHashedSecret(\hash('sha256', $plainToken));

        if (null === $apiKey || $apiKey->isRevoked()) {
            return null;
        }

        $account = $apiKey->account();

        if (!$account->isActive()) {
            return null;
        }

        $apiKey->touchLastUsed();
        $this->apiKeyRepository->save($apiKey);

        return new ValidatedApiKey(
            apiKeyId: $apiKey->id()->asString(),
            accountId: $account->id()->asString(),
            email: $account->email(),
            symfonyRoles: [$account->role()->toSymfonyRole()],
        );
    }
}
