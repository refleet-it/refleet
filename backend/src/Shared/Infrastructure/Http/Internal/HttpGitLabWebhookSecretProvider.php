<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Internal;

use App\Shared\Domain\Service\GitLabWebhookSecretProviderInterface;
use App\Shared\Infrastructure\Http\InternalApiClient;

/**
 * GitLabWebhookSecretProviderInterface adapter for every context but Organization, which
 * implements it directly. See docs/adr/0001-multiple-kernels.md.
 */
final readonly class HttpGitLabWebhookSecretProvider implements GitLabWebhookSecretProviderInterface
{
    private string $organizationInternalUrl;

    public function __construct(
        private InternalApiClient $client,
    ) {
        $url = $_ENV['ORGANIZATION_INTERNAL_URL'] ?? throw new \InvalidArgumentException('ORGANIZATION_INTERNAL_URL environment variable is not set');
        if (!\is_string($url) || '' === $url) {
            throw new \InvalidArgumentException('ORGANIZATION_INTERNAL_URL must be a non-empty string');
        }

        $this->organizationInternalUrl = $url;
    }

    #[\Override]
    public function forOrganization(string $organizationId): ?string
    {
        $data = $this->client->get($this->organizationInternalUrl, '/internal/gitlab-webhook-secret/'.$organizationId);
        $secret = $data['secret'] ?? null;

        return \is_string($secret) ? $secret : null;
    }
}
