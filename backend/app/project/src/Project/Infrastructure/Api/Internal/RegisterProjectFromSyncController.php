<?php

declare(strict_types=1);

namespace App\Project\Project\Infrastructure\Api\Internal;

use App\Shared\Domain\Service\ProjectRegistryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Backs App\Shared\Infrastructure\Http\Internal\HttpProjectRegistry, the adapter
 * Organization uses for ProjectRegistryInterface::register() — see
 * docs/adr/0001-multiple-kernels.md.
 */
#[Route('/projects/register', name: 'internal_project_register', methods: ['POST'])]
final readonly class RegisterProjectFromSyncController
{
    public function __construct(
        private ProjectRegistryInterface $projectRegistry,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{organizationId: string, externalId: string, name: string, path: string, webUrl?: string, defaultBranch?: string, description?: string} $payload */
        $payload = \json_decode($request->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $this->projectRegistry->register(
            organizationId: $payload['organizationId'],
            externalId: $payload['externalId'],
            name: $payload['name'],
            path: $payload['path'],
            webUrl: $payload['webUrl'] ?? null,
            defaultBranch: $payload['defaultBranch'] ?? null,
            description: $payload['description'] ?? null,
        );

        return new JsonResponse(['registered' => true]);
    }
}
