<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\RevokeApiKey;

use App\Identity\Account\Domain\ApiKey\Exception\ApiKeyNotFoundException;
use App\Identity\Account\Domain\ApiKey\Repository\ApiKeyRepositoryInterface;
use App\Identity\Account\Domain\ApiKey\ValueObject\ApiKeyId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevokeApiKeyHandler
{
    public function __construct(
        private ApiKeyRepositoryInterface $repository,
    ) {
    }

    public function __invoke(RevokeApiKeyCommand $command): void
    {
        $apiKey = $this->repository->findById(ApiKeyId::fromString($command->apiKeyId));

        if (null === $apiKey || $apiKey->accountId() !== $command->accountId) {
            throw new ApiKeyNotFoundException();
        }

        $apiKey->revoke();

        $this->repository->save($apiKey);
    }
}
