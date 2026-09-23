<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Service;

use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use App\Shared\Domain\Service\GitLabWebhookSecretProviderInterface;

final readonly class GitLabWebhookSecretProvider implements GitLabWebhookSecretProviderInterface
{
    public function __construct(
        private GitLabConnectionRepositoryInterface $connections,
    ) {
    }

    #[\Override]
    public function forOrganization(string $organizationId): ?string
    {
        return $this->connections
            ->findByOrganizationId(OrganizationId::fromString($organizationId))
            ?->webhookSecret();
    }
}
