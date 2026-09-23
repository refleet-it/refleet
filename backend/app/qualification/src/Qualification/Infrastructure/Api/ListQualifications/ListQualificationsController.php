<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\ListQualifications;

use App\Qualification\Qualification\Application\Query\ListQualifications\ListQualificationsQuery;
use App\Qualification\Qualification\Application\Query\ListQualifications\QualificationOverview;
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

#[Route('/qualifications', name: 'qualification_list', methods: ['GET'])]
#[OA\Get(
    description: "List the current account's organization qualifications, newest first, each with a progress percentage derived from its targets' status breakdown.",
    summary: 'List Qualifications',
    tags: ['Qualification'],
    parameters: [
        new OA\Parameter(name: 'page', description: 'Page number (default 1)', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        new OA\Parameter(name: 'limit', description: 'Number of items per page (default 20)', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        new OA\Parameter(name: 'sortBy', description: 'Sort field', in: 'query', schema: new OA\Schema(type: 'string', default: 'createdAt')),
        new OA\Parameter(name: 'sortDirection', description: 'Sort order (asc/desc)', in: 'query', schema: new OA\Schema(type: 'string', default: 'desc')),
        new OA\Parameter(name: 'search', description: 'Filter by title (contains, case-sensitive per DB collation)', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'status', description: 'Filter by exact status', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'archived', description: 'List archived qualifications instead of live ones', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Page of qualifications'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class ListQualificationsController
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

        $handledStamp = $this->bus->dispatch(new ListQualificationsQuery(
            organizationId: $organization->id,
            page: $parameters->getPagination()->getPage(),
            limit: $parameters->getPagination()->getLimit(),
            sortBy: $parameters->getSorting()?->getField(),
            sortDirection: $parameters->getSorting()?->getDirection()->value,
            search: '' !== $search ? $search : null,
            status: '' !== $status ? $status : null,
            archived: $request->query->getBoolean('archived'),
        ))->last(HandledStamp::class);

        /** @var ListResponse<QualificationOverview> $result */
        $result = $handledStamp?->getResult();

        return $this->toResponse($result);
    }

    /**
     * @param ListResponse<QualificationOverview> $result
     */
    private function toResponse(ListResponse $result): JsonResponse
    {
        return new JsonResponse([
            'qualifications' => \array_map(static fn (QualificationOverview $qualification): array => [
                'id' => $qualification->id,
                'title' => $qualification->title,
                'description' => $qualification->description,
                'status' => $qualification->status,
                'qualificationMode' => $qualification->qualificationMode,
                'targetCount' => $qualification->targetCount,
                'terminalTargetCount' => $qualification->terminalTargetCount,
                'statusBreakdown' => $qualification->statusBreakdown,
                'progressPercent' => $qualification->progressPercent,
                'createdAt' => $qualification->createdAt,
                'archivedAt' => $qualification->archivedAt,
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
