<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Api\Internal;

use App\Shared\Domain\Service\MergeRequestPipelineReaderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Backs App\Shared\Infrastructure\Http\Internal\HttpMergeRequestPipelineReader, the adapter
 * Shift uses for MergeRequestPipelineReaderInterface — see docs/adr/0001-multiple-kernels.md.
 */
#[Route('/gitlab/merge-request-pipelines/{organizationId}', name: 'internal_gitlab_merge_request_pipelines', methods: ['POST'])]
final readonly class GetMergeRequestPipelinesController
{
    public function __construct(
        private MergeRequestPipelineReaderInterface $mergeRequestPipelines,
    ) {
    }

    public function __invoke(string $organizationId, Request $request): JsonResponse
    {
        /** @var array{iidsByProject: array<string, list<string>>} $payload */
        $payload = \json_decode($request->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return new JsonResponse([
            'pipelines' => $this->mergeRequestPipelines->pipelinesFor($organizationId, $payload['iidsByProject'] ?? []),
        ]);
    }
}
