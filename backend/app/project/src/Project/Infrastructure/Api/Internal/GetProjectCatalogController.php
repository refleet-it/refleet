<?php

declare(strict_types=1);

namespace App\Project\Project\Infrastructure\Api\Internal;

use App\Shared\Domain\Service\ProjectCatalogInterface;
use App\Shared\Domain\ValueObject\ProjectCatalogEntry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Backs App\Shared\Infrastructure\Http\Internal\HttpProjectCatalog, the adapter
 * Qualification and Shift use for ProjectCatalogInterface — see
 * docs/adr/0001-multiple-kernels.md. `filtered` decides which of the interface's two
 * methods to call rather than the mere presence of `projectIds`: an empty array query
 * param is indistinguishable from an absent one once URL-encoded (http_build_query drops
 * it entirely), so an explicit empty selection needs its own signal to stay distinguishable
 * from "no filter at all".
 */
#[Route('/projects/{organizationId}', name: 'internal_project_catalog', methods: ['GET'])]
final readonly class GetProjectCatalogController
{
    public function __construct(
        private ProjectCatalogInterface $projectCatalog,
    ) {
    }

    public function __invoke(string $organizationId, Request $request): JsonResponse
    {
        if ($request->query->getBoolean('filtered')) {
            /** @var list<string> $projectIds */
            $projectIds = $request->query->all('projectIds');
            $entries = $this->projectCatalog->byIds($organizationId, $projectIds);
        } else {
            $entries = $this->projectCatalog->allForOrganization($organizationId);
        }

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
