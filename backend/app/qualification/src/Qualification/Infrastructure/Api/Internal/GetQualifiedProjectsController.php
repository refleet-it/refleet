<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\Internal;

use App\Shared\Domain\Service\QualifiedProjectsInterface;
use App\Shared\Domain\ValueObject\ProjectCatalogEntry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Backs App\Shared\Infrastructure\Http\Internal\HttpQualifiedProjects, the adapter Shift
 * uses for QualifiedProjectsInterface — see docs/adr/0001-multiple-kernels.md. `filtered`
 * decides whether forQualification() gets null or an array, rather than the mere presence
 * of `projectIds`: an empty array query param is indistinguishable from an absent one once
 * URL-encoded (http_build_query drops it entirely), so an explicit empty selection needs
 * its own signal to stay distinguishable from "no filter at all" — see that interface's own
 * docblock.
 */
#[Route('/qualified-projects/{organizationId}/{qualificationId}', name: 'internal_qualified_projects', methods: ['GET'])]
final readonly class GetQualifiedProjectsController
{
    public function __construct(
        private QualifiedProjectsInterface $qualifiedProjects,
    ) {
    }

    public function __invoke(string $organizationId, string $qualificationId, Request $request): JsonResponse
    {
        $projectIds = null;
        if ($request->query->getBoolean('filtered')) {
            /** @var list<string> $projectIds */
            $projectIds = $request->query->all('projectIds');
        }

        $entries = $this->qualifiedProjects->forQualification($organizationId, $qualificationId, $projectIds);

        return new JsonResponse(['entries' => \array_map($this->toArray(...), $entries)]);
    }

    /**
     * @return array{id: string, externalId: string, name: string, path: string, defaultBranch: ?string}
     */
    private function toArray(ProjectCatalogEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'externalId' => $entry->externalId,
            'name' => $entry->name,
            'path' => $entry->path,
            'defaultBranch' => $entry->defaultBranch,
        ];
    }
}
