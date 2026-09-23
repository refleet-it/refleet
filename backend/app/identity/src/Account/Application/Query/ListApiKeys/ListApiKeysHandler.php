<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Query\ListApiKeys;

use App\Identity\Account\Application\Query\ApiKeyReadModel;
use App\Identity\Account\Domain\ApiKey\Model\ApiKey;
use App\Identity\Account\Domain\ApiKey\Repository\ApiKeyRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListApiKeysHandler
{
    public function __construct(
        private ApiKeyRepositoryInterface $repository,
    ) {
    }

    /**
     * @return ApiKeyReadModel[]
     */
    public function __invoke(ListApiKeysQuery $query): array
    {
        $apiKeys = $this->repository->findAllByAccountId($query->accountId);

        return \array_map($this->createReadModel(...), $apiKeys);
    }

    private function createReadModel(ApiKey $apiKey): ApiKeyReadModel
    {
        return new ApiKeyReadModel(
            id: $apiKey->id()->asString(),
            name: $apiKey->name(),
            prefix: $apiKey->keyPrefix(),
            createdAt: $apiKey->createdAt()->format('c'),
            lastUsedAt: $apiKey->lastUsedAt()?->format('c'),
            revokedAt: $apiKey->revokedAt()?->format('c'),
        );
    }
}
