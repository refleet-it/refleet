<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\CreateApiKey;

use App\Identity\Account\Domain\Account\Exception\AccountNotFoundException;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\ApiKey\Model\ApiKey;
use App\Identity\Account\Domain\ApiKey\Repository\ApiKeyRepositoryInterface;
use App\Identity\Account\Domain\ApiKey\ValueObject\ApiKeyId;
use App\Identity\Account\Infrastructure\Security\ApiKeyTokenGenerator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateApiKeyHandler
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository,
        private ApiKeyRepositoryInterface $apiKeyRepository,
        private ApiKeyTokenGenerator $tokenGenerator,
    ) {
    }

    public function __invoke(CreateApiKeyCommand $command): CreatedApiKey
    {
        $account = $this->accountRepository->findById(AccountId::fromString($command->accountId));

        if (null === $account) {
            throw new AccountNotFoundException();
        }

        $id = ApiKeyId::generate();
        $generated = $this->tokenGenerator->generate();

        $apiKey = ApiKey::create(
            id: $id,
            account: $account,
            name: $command->name,
            keyPrefix: $generated->prefix,
            hashedSecret: $generated->hashedSecret,
        );

        $this->apiKeyRepository->save($apiKey);

        return new CreatedApiKey(
            id: $id->asString(),
            name: $apiKey->name(),
            prefix: $generated->prefix,
            token: $generated->plainToken,
            createdAt: $apiKey->createdAt()->format('c'),
        );
    }
}
