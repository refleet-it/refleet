<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\RemoveEmployee;

use App\Organization\Organization\Application\Command\RemoveEmployee\RemoveEmployeeCommand;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/organizations/employees/{accountId}', name: 'organization_remove_employee', requirements: ['accountId' => Requirements::UUID], methods: ['DELETE'])]
#[OA\Delete(
    description: "Remove an employee from the current account's organization. Only the organization owner may do this, and the owner cannot remove themselves.",
    summary: 'Remove Employee',
    tags: ['Organization'],
    parameters: [
        new OA\Parameter(name: 'accountId', description: 'Employee account ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Employee removed'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Only the organization owner can remove employees, or the owner tried to remove themselves'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'No such employee in this organization'),
    ]
)]
final readonly class RemoveEmployeeController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        string $accountId,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $this->bus->dispatch(new RemoveEmployeeCommand(
            requestingAccountId: $user->getUserId()->asString(),
            targetAccountId: $accountId,
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
