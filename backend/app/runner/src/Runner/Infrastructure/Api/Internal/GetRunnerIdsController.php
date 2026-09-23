<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\Internal;

use App\Shared\Domain\Service\RunnerDirectoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Backs App\Shared\Infrastructure\Http\Internal\HttpRunnerDirectory, the adapter
 * Qualification and Shift use for RunnerDirectoryInterface — see
 * docs/adr/0001-multiple-kernels.md.
 */
#[Route('/runner-ids/{organizationId}', name: 'internal_runner_ids', methods: ['GET'])]
final readonly class GetRunnerIdsController
{
    public function __construct(
        private RunnerDirectoryInterface $runnerDirectory,
    ) {
    }

    public function __invoke(string $organizationId): JsonResponse
    {
        return new JsonResponse(['idsByName' => $this->runnerDirectory->idsByNameForOrganization($organizationId)]);
    }
}
