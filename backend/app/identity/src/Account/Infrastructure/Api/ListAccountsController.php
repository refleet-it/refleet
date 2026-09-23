<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api;

use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Shared\Infrastructure\Service\CursorRequestParametersParser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/identity/accounts', name: 'identity_accounts_list', methods: ['GET'], priority: 100)]
#[IsGranted('ROLE_ADMINISTRATOR')]
#[OA\Get(
    description: 'Get paginated list of accounts',
    summary: 'List Accounts',
    tags: ['Identity Account'],
    parameters: [
        new OA\Parameter(name: 'limit', description: 'Number of items per page', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        new OA\Parameter(name: 'cursor', description: 'Cursor for pagination', in: 'query', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'sortBy', description: 'Sort field', in: 'query', schema: new OA\Schema(type: 'string', default: 'email')),
        new OA\Parameter(name: 'sortDirection', description: 'Sort order (asc/desc)', in: 'query', schema: new OA\Schema(type: 'string', default: 'asc')),
    ],
    responses: [
        new OA\Response(
            response: Response::HTTP_OK,
            description: 'Accounts list retrieved successfully',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                ref: new OA\Schema(schema: 'AccountReadModel')
                            )
                        ),
                        new OA\Property(
                            property: 'pagination',
                            properties: [
                                new OA\Property(property: 'hasNextPage', type: 'boolean'),
                                new OA\Property(property: 'nextCursor', type: 'string', nullable: true),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            )
        ),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions'),
    ]
)]
final readonly class ListAccountsController
{
    private const array ALLOWED_SORT_FIELDS = ['email', 'role', 'createdAt', 'updatedAt'];

    private const array ALLOWED_FILTER_FIELDS = ['email', 'role', 'status'];

    public function __construct(
        private AccountRepositoryInterface $accountRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $parser = CursorRequestParametersParser::create(
            allowedSortFields: self::ALLOWED_SORT_FIELDS,
            allowedFilterFields: self::ALLOWED_FILTER_FIELDS,
        );
        $parameters = $parser->parseFromRequest($request);

        $result = $this->accountRepository->getPaginatedList($parameters);

        $accounts = \array_map(
            static fn ($account) => new AccountReadModel(
                id: $account->id()->asString(),
                email: $account->email(),
                role: $account->role()->value,
                status: $account->isActive() ? 'active' : 'inactive',
                createdAt: $account->createdAt()->format('c'),
                updatedAt: $account->updatedAt()->format('c'),
            ),
            $result->getItems()
        );

        return new JsonResponse([
            'data' => $accounts,
            'pagination' => [
                'hasNextPage' => $result->hasNextPage(),
                'nextCursor' => $result->getNextCursor(),
            ],
        ], Response::HTTP_OK);
    }
}
