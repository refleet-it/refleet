<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\ClaimCliAuthorization;

use App\Identity\Account\Application\Command\CreateApiKey\CreatedApiKey;
use App\Identity\Account\Domain\ApiKey\Model\ApiKey;
use App\Identity\Account\Domain\ApiKey\Repository\ApiKeyRepositoryInterface;
use App\Identity\Account\Domain\ApiKey\ValueObject\ApiKeyId;
use App\Identity\Account\Domain\CliAuthorization\Enum\CliAuthorizationStatusEnum;
use App\Identity\Account\Domain\CliAuthorization\Exception\CliAuthorizationNotFoundException;
use App\Identity\Account\Domain\CliAuthorization\Model\CliAuthorization;
use App\Identity\Account\Domain\CliAuthorization\Repository\CliAuthorizationRepositoryInterface;
use App\Identity\Account\Infrastructure\Security\ApiKeyTokenGenerator;
use App\Identity\Account\Infrastructure\Security\CliAuthorizationCodeGenerator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The CLI's poll. Minting the API key here, at pick-up, rather than when the browser
 * approves means the plaintext token never has to sit in the database waiting to be
 * collected — the approval only records *who* said yes.
 */
#[AsMessageHandler]
final readonly class ClaimCliAuthorizationHandler
{
    public function __construct(
        private CliAuthorizationRepositoryInterface $authorizations,
        private ApiKeyRepositoryInterface $apiKeys,
        private ApiKeyTokenGenerator $tokenGenerator,
    ) {
    }

    public function __invoke(ClaimCliAuthorizationCommand $command): ClaimedCliAuthorization
    {
        $authorization = $this->authorizations->findByDeviceSecretHash(CliAuthorizationCodeGenerator::hash($command->deviceSecret));
        if (null === $authorization) {
            throw new CliAuthorizationNotFoundException();
        }

        if ($authorization->isExpired()) {
            $this->authorizations->delete($authorization);

            return new ClaimedCliAuthorization(ClaimedCliAuthorization::EXPIRED);
        }

        return match ($authorization->status()) {
            CliAuthorizationStatusEnum::PENDING => new ClaimedCliAuthorization(ClaimedCliAuthorization::PENDING),
            CliAuthorizationStatusEnum::DENIED => $this->finish($authorization, new ClaimedCliAuthorization(ClaimedCliAuthorization::DENIED)),
            CliAuthorizationStatusEnum::APPROVED => $this->finish($authorization, $this->mintApiKey($authorization, $command->apiKeyName)),
        };
    }

    private function mintApiKey(CliAuthorization $authorization, string $name): ClaimedCliAuthorization
    {
        $account = $authorization->account();
        if (null === $account) {
            throw new CliAuthorizationNotFoundException();
        }

        $id = ApiKeyId::generate();
        $generated = $this->tokenGenerator->generate();
        $apiKey = ApiKey::create(
            id: $id,
            account: $account,
            name: $name,
            keyPrefix: $generated->prefix,
            hashedSecret: $generated->hashedSecret,
        );
        $this->apiKeys->save($apiKey);

        return new ClaimedCliAuthorization(
            status: ClaimedCliAuthorization::APPROVED,
            apiKey: new CreatedApiKey(
                id: $id->asString(),
                name: $apiKey->name(),
                prefix: $generated->prefix,
                token: $generated->plainToken,
                createdAt: $apiKey->createdAt()->format('c'),
            ),
            accountEmail: $account->email(),
        );
    }

    private function finish(CliAuthorization $authorization, ClaimedCliAuthorization $result): ClaimedCliAuthorization
    {
        $this->authorizations->delete($authorization);

        return $result;
    }
}
