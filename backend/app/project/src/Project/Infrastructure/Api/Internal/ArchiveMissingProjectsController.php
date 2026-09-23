<?php

declare(strict_types=1);

namespace App\Project\Project\Infrastructure\Api\Internal;

use App\Shared\Domain\Service\ProjectRegistryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Backs App\Shared\Infrastructure\Http\Internal\HttpProjectRegistry, the adapter
 * Organization uses for ProjectRegistryInterface::archiveMissing() — see
 * docs/adr/0001-multiple-kernels.md.
 */
#[Route('/projects/archive-missing', name: 'internal_project_archive_missing', methods: ['POST'])]
final readonly class ArchiveMissingProjectsController
{
    public function __construct(
        private ProjectRegistryInterface $projectRegistry,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{organizationId: string, seenExternalIds: string[]} $payload */
        $payload = \json_decode($request->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $archivedCount = $this->projectRegistry->archiveMissing($payload['organizationId'], $payload['seenExternalIds']);

        return new JsonResponse(['archivedCount' => $archivedCount]);
    }
}
