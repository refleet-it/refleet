<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\ListRunners;

use App\Runner\Runner\Application\Query\ListRunners\ListRunnersQuery;
use App\Runner\Runner\Application\Query\ListRunners\RunnerOverview;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Infrastructure\Service\RequestParametersParser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/runners', name: 'runner_list', methods: ['GET'])]
#[OA\Get(
    description: "List the current account's organization runners, identified by name, with their current status.",
    summary: 'List Runners',
    tags: ['Runner'],
    parameters: [
        new OA\Parameter(name: 'page', description: 'Page number (default 1)', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        new OA\Parameter(name: 'limit', description: 'Number of items per page (default 20)', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        new OA\Parameter(name: 'sortBy', description: 'Sort field', in: 'query', schema: new OA\Schema(type: 'string', default: 'name')),
        new OA\Parameter(name: 'sortDirection', description: 'Sort order (asc/desc)', in: 'query', schema: new OA\Schema(type: 'string', default: 'asc')),
        new OA\Parameter(name: 'archived', description: 'List archived runners instead of live ones', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Page of runners'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class ListRunnersController
{
    private const array ALLOWED_SORT_FIELDS = ['name', 'createdAt', 'lastSeenAt'];

    public function __construct(
        private MessageBusInterface $bus,
        private OrganizationContextProviderInterface $organizationContext,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        AccountUser $user,
        Request $request,
    ): JsonResponse {
        $organization = $this->organizationContext->requireForAccount($user->getUserId());

        $parser = RequestParametersParser::create(allowedSortFields: self::ALLOWED_SORT_FIELDS);
        $parameters = $parser->parseSimpleFromRequest($request);

        $handledStamp = $this->bus->dispatch(new ListRunnersQuery(
            organizationId: $organization->id,
            page: $parameters->getPagination()->getPage(),
            limit: $parameters->getPagination()->getLimit(),
            sortBy: $parameters->getSorting()?->getField(),
            sortDirection: $parameters->getSorting()?->getDirection()->value,
            archived: $request->query->getBoolean('archived'),
        ))->last(HandledStamp::class);

        /** @var ListResponse<RunnerOverview> $result */
        $result = $handledStamp?->getResult();

        return $this->toResponse($result);
    }

    /**
     * @param ListResponse<RunnerOverview> $result
     */
    private function toResponse(ListResponse $result): JsonResponse
    {
        return new JsonResponse([
            'runners' => \array_map(static fn (RunnerOverview $runner): array => [
                'id' => $runner->id,
                'name' => $runner->name,
                'status' => $runner->status,
                'lastSeenAt' => $runner->lastSeenAt,
                'createdAt' => $runner->createdAt,
                'archivedAt' => $runner->archivedAt,
                'supportedEngines' => $runner->supportedEngines,
                'supportedModels' => $runner->supportedModels,
                'usage' => $runner->usage,
                'version' => $runner->version,
                'latestVersion' => $runner->latestVersion,
                'updateAvailable' => $runner->updateAvailable,
                'updateRequestedAt' => $runner->updateRequestedAt,
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
