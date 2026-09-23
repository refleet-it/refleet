<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\ListPendingInvitations;

use App\Organization\Organization\Application\Query\ListPendingInvitations\ListPendingInvitationsQuery;
use App\Organization\Organization\Application\Query\ListPendingInvitations\PendingInvitationOverview;
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

#[Route('/organizations/invitations', name: 'organization_list_pending_invitations', methods: ['GET'])]
#[OA\Get(
    description: "List the current account's organization pending (not yet accepted, not expired) invitations. Only the organization owner may do this.",
    summary: 'List Pending Invitations',
    tags: ['Organization'],
    parameters: [
        new OA\Parameter(name: 'page', description: 'Page number (default 1)', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        new OA\Parameter(name: 'limit', description: 'Number of items per page (default 20)', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        new OA\Parameter(name: 'sortBy', description: 'Sort field', in: 'query', schema: new OA\Schema(type: 'string', default: 'createdAt')),
        new OA\Parameter(name: 'sortDirection', description: 'Sort order (asc/desc)', in: 'query', schema: new OA\Schema(type: 'string', default: 'desc')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Page of pending invitations'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Only the organization owner can list invitations'),
    ]
)]
final readonly class ListPendingInvitationsController
{
    private const array ALLOWED_SORT_FIELDS = ['email', 'expiresAt', 'createdAt'];

    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        AccountUser $user,
        Request $request,
    ): JsonResponse {
        $parser = RequestParametersParser::create(allowedSortFields: self::ALLOWED_SORT_FIELDS);
        $parameters = $parser->parseSimpleFromRequest($request);

        $handledStamp = $this->bus->dispatch(new ListPendingInvitationsQuery(
            requestingAccountId: $user->getUserId()->asString(),
            page: $parameters->getPagination()->getPage(),
            limit: $parameters->getPagination()->getLimit(),
            sortBy: $parameters->getSorting()?->getField(),
            sortDirection: $parameters->getSorting()?->getDirection()->value,
        ))->last(HandledStamp::class);

        /** @var ListResponse<PendingInvitationOverview> $result */
        $result = $handledStamp?->getResult();

        return new JsonResponse([
            'invitations' => \array_map(static fn (PendingInvitationOverview $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'createdAt' => $invitation->createdAt,
                'expiresAt' => $invitation->expiresAt,
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
