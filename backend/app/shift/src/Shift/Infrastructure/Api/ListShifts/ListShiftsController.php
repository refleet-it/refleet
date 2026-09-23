<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\ListShifts;

use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Infrastructure\Service\RequestParametersParser;
use App\Shift\Shift\Application\Query\ListShifts\ListShiftsQuery;
use App\Shift\Shift\Application\Query\ListShifts\ShiftOverview;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/shifts', name: 'shift_list', methods: ['GET'])]
#[OA\Get(
    description: "List the current account's organization shifts, newest first, each with a progress percentage derived from its targets' status breakdown.",
    summary: 'List Shifts',
    tags: ['Shift'],
    parameters: [
        new OA\Parameter(name: 'page', description: 'Page number (default 1)', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        new OA\Parameter(name: 'limit', description: 'Number of items per page (default 20)', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        new OA\Parameter(name: 'sortBy', description: 'Sort field', in: 'query', schema: new OA\Schema(type: 'string', default: 'createdAt')),
        new OA\Parameter(name: 'sortDirection', description: 'Sort order (asc/desc)', in: 'query', schema: new OA\Schema(type: 'string', default: 'desc')),
        new OA\Parameter(name: 'search', description: 'Filter by title (contains, case-sensitive per DB collation)', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'status', description: 'Filter by exact status', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'archived', description: 'List archived shifts instead of live ones', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Page of shifts'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class ListShiftsController
{
    private const array ALLOWED_SORT_FIELDS = ['title', 'status', 'createdAt'];

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

        $search = $request->query->getString('search');
        $status = $request->query->getString('status');

        $handledStamp = $this->bus->dispatch(new ListShiftsQuery(
            organizationId: $organization->id,
            page: $parameters->getPagination()->getPage(),
            limit: $parameters->getPagination()->getLimit(),
            sortBy: $parameters->getSorting()?->getField(),
            sortDirection: $parameters->getSorting()?->getDirection()->value,
            search: '' !== $search ? $search : null,
            status: '' !== $status ? $status : null,
            archived: $request->query->getBoolean('archived'),
        ))->last(HandledStamp::class);

        /** @var ListResponse<ShiftOverview> $result */
        $result = $handledStamp?->getResult();

        return $this->toResponse($result);
    }

    /**
     * @param ListResponse<ShiftOverview> $result
     */
    private function toResponse(ListResponse $result): JsonResponse
    {
        return new JsonResponse([
            'shifts' => \array_map(static fn (ShiftOverview $shift): array => [
                'id' => $shift->id,
                'title' => $shift->title,
                'description' => $shift->description,
                'status' => $shift->status,
                'qualificationId' => $shift->qualificationId,
                'changeMode' => $shift->changeMode,
                'targetCount' => $shift->targetCount,
                'terminalTargetCount' => $shift->terminalTargetCount,
                'statusBreakdown' => $shift->statusBreakdown,
                'progressPercent' => $shift->progressPercent,
                'createdAt' => $shift->createdAt,
                'archivedAt' => $shift->archivedAt,
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
