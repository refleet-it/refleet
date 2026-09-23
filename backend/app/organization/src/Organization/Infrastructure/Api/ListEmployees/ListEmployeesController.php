<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\ListEmployees;

use App\Organization\Organization\Application\Query\ListEmployees\EmployeeOverview;
use App\Organization\Organization\Application\Query\ListEmployees\ListEmployeesQuery;
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

#[Route('/organizations/employees', name: 'organization_list_employees', methods: ['GET'])]
#[OA\Get(
    description: "List the current account's organization employees.",
    summary: 'List Employees',
    tags: ['Organization'],
    parameters: [
        new OA\Parameter(name: 'page', description: 'Page number (default 1)', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        new OA\Parameter(name: 'limit', description: 'Number of items per page (default 20)', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        new OA\Parameter(name: 'sortBy', description: 'Sort field', in: 'query', schema: new OA\Schema(type: 'string', default: 'email')),
        new OA\Parameter(name: 'sortDirection', description: 'Sort order (asc/desc)', in: 'query', schema: new OA\Schema(type: 'string', default: 'asc')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Page of employees'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account does not belong to an organization'),
    ]
)]
final readonly class ListEmployeesController
{
    private const array ALLOWED_SORT_FIELDS = ['email', 'role', 'joinedOrganizationAt'];

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

        $handledStamp = $this->bus->dispatch(new ListEmployeesQuery(
            organizationId: $organization->id,
            page: $parameters->getPagination()->getPage(),
            limit: $parameters->getPagination()->getLimit(),
            sortBy: $parameters->getSorting()?->getField(),
            sortDirection: $parameters->getSorting()?->getDirection()->value,
        ))->last(HandledStamp::class);

        /** @var ListResponse<EmployeeOverview> $result */
        $result = $handledStamp?->getResult();

        return $this->toResponse($result);
    }

    /**
     * @param ListResponse<EmployeeOverview> $result
     */
    private function toResponse(ListResponse $result): JsonResponse
    {
        return new JsonResponse([
            'employees' => \array_map(static fn (EmployeeOverview $employee): array => [
                'accountId' => $employee->accountId,
                'email' => $employee->email,
                'role' => $employee->role,
                'joinedAt' => $employee->joinedAt,
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
