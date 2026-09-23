<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Api\Internal;

use App\Shared\Domain\Service\GitLabWebhookSecretProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Backs App\Shared\Infrastructure\Http\Internal\HttpGitLabWebhookSecretProvider, the
 * adapter Shift uses for GitLabWebhookSecretProviderInterface — see
 * docs/adr/0001-multiple-kernels.md.
 */
#[Route('/gitlab-webhook-secret/{organizationId}', name: 'internal_gitlab_webhook_secret', methods: ['GET'])]
final readonly class GetGitLabWebhookSecretController
{
    public function __construct(
        private GitLabWebhookSecretProviderInterface $secretProvider,
    ) {
    }

    public function __invoke(string $organizationId): JsonResponse
    {
        return new JsonResponse(['secret' => $this->secretProvider->forOrganization($organizationId)]);
    }
}
