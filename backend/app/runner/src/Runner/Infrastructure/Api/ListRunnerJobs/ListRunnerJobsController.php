<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\ListRunnerJobs;

use App\Runner\Runner\Application\Query\ListRunnerJobs\ListRunnerJobsQuery;
use App\Runner\Runner\Application\Query\ListRunnerJobs\RunnerJobOverview;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/runners/{id}/jobs', name: 'runner_job_list', requirements: ['id' => Requirements::UUID], methods: ['GET'])]
#[OA\Get(
    description: 'List the jobs (qualification and change work items) claimed by a runner.',
    summary: 'List Runner Jobs',
    tags: ['Runner'],
    parameters: [
        new OA\Parameter(name: 'id', description: 'Runner ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        new OA\Parameter(name: 'page', description: 'Page number (default 1)', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        new OA\Parameter(name: 'limit', description: 'Number of items per page (default 20)', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Page of runner jobs'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Runner not found'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class ListRunnerJobsController
{
    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        string $id,
        #[CurrentUser]
        AccountUser $user,
        Request $request,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $page = $request->query->getInt('page');
        $limit = $request->query->getInt('limit');
        $pagination = PaginationParameters::fromRequest($page > 0 ? $page : null, $limit > 0 ? $limit : null);

        $handledStamp = $this->bus->dispatch(new ListRunnerJobsQuery(
            runnerId: $id,
            organizationId: $organization->id,
            page: $pagination->getPage(),
            limit: $pagination->getLimit(),
        ))->last(HandledStamp::class);

        /** @var ListResponse<RunnerJobOverview> $result */
        $result = $handledStamp?->getResult();

        return $this->toResponse($result);
    }

    /**
     * @param ListResponse<RunnerJobOverview> $result
     */
    private function toResponse(ListResponse $result): JsonResponse
    {
        return new JsonResponse([
            'jobs' => \array_map(static fn (RunnerJobOverview $job): array => [
                'id' => $job->id,
                'kind' => $job->kind,
                'mode' => $job->mode,
                'status' => $job->status,
                'ownerId' => $job->ownerId,
                'ownerLabel' => $job->ownerLabel,
                'ownerTargetId' => $job->ownerTargetId,
                'projectName' => $job->projectName,
                'attemptCount' => $job->attemptCount,
                'resultSummary' => $job->resultSummary,
                'errorMessage' => $job->errorMessage,
                'createdAt' => $job->createdAt,
                'claimedAt' => $job->claimedAt,
                'completedAt' => $job->completedAt,
            ], $result->getItems()),
            'pagination' => [
                'page' => $result->getPagination()->getPage(),
                'limit' => $result->getPagination()->getLimit(),
                'total' => $result->getTotalItems(),
                'totalPages' => $result->getTotalPages(),
                'hasNextPage' => $result->hasNextPage(),
                'hasPreviousPage' => $result->hasPreviousPage(),
            ],
        ], Response::HTTP_OK);
    }
}
