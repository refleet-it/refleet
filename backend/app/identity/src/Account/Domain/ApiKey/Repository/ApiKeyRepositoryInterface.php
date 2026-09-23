<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\ApiKey\Repository;

use App\Identity\Account\Domain\ApiKey\Model\ApiKey;
use App\Identity\Account\Domain\ApiKey\ValueObject\ApiKeyId;

interface ApiKeyRepositoryInterface
{
    public function save(ApiKey $apiKey): void;

    public function findById(ApiKeyId $id): ?ApiKey;

    public function findByHashedSecret(string $hashedSecret): ?ApiKey;

    /**
     * @return ApiKey[]
     */
    public function findAllByAccountId(string $accountId): array;
}
