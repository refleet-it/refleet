<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Bus\RunnerArchived;

use App\Identity\Account\Domain\ApiKey\Repository\ApiKeyRepositoryInterface;
use App\Identity\Account\Domain\ApiKey\ValueObject\ApiKeyId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Retires the archived runner's API key. Revocation is permanent — ApiKey has no way back
 * — which is what makes archiving irreversible for that credential.
 */
#[AsMessageHandler]
final readonly class RunnerArchivedMessageHandler
{
    public function __construct(
        private ApiKeyRepositoryInterface $apiKeys,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RunnerArchivedMessage $message): void
    {
        if (null === $message->apiKeyId) {
            $this->logger->info('Archived runner had no API key on record, nothing to revoke', [
                'runnerId' => $message->runnerId,
            ]);

            return;
        }

        $apiKey = $this->apiKeys->findById(ApiKeyId::fromString($message->apiKeyId));

        if (null === $apiKey) {
            $this->logger->warning('API key of an archived runner no longer exists', [
                'runnerId' => $message->runnerId,
                'apiKeyId' => $message->apiKeyId,
            ]);

            return;
        }

        $apiKey->revoke();
        $this->apiKeys->save($apiKey);

        $this->logger->info('Revoked the API key of an archived runner', [
            'runnerId' => $message->runnerId,
            'apiKeyId' => $message->apiKeyId,
        ]);
    }
}
