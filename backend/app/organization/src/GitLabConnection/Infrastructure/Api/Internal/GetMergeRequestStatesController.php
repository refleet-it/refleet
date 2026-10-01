<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Api\Internal;

use App\Shared\Domain\Service\MergeRequestStateReaderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Backs App\Shared\Infrastructure\Http\Internal\HttpMergeRequestStateReader, the adapter
 * Shift uses for MergeRequestStateReaderInterface — see docs/adr/0001-multiple-kernels.md.
 *
 * POST rather than GET because the iids to look up are a map keyed by project, which does
 * not fit a query string a poller can send for a few hundred merge requests at a time.
 */
#[Route('/gitlab/merge-request-states/{organizationId}', name: 'internal_gitlab_merge_request_states', methods: ['POST'])]
final readonly class GetMergeRequestStatesController
{
    public function __construct(
        private MergeRequestStateReaderInterface $mergeRequestStates,
    ) {
    }

    public function __invoke(string $organizationId, Request $request): JsonResponse
    {
        /** @var array{iidsByProject: array<string, list<string>>} $payload */
        $payload = \json_decode($request->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return new JsonResponse([
            'states' => $this->mergeRequestStates->statesFor($organizationId, $payload['iidsByProject'] ?? []),
        ]);
    }
}
