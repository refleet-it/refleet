<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

/**
 * Supplies the shared secret GitLab signs its webhooks with, so contexts receiving those
 * webhooks can verify them without depending on the context that owns the connection.
 */
interface GitLabWebhookSecretProviderInterface
{
    public function forOrganization(string $organizationId): ?string;
}
