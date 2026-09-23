<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\ChangeEmployeeRole;

use App\Organization\Organization\Application\Command\ChangeEmployeeRole\ChangeEmployeeRoleCommand;
use App\Organization\Organization\Application\Command\ChangeEmployeeRole\TransferredOwnership;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/organizations/employees/{accountId}/owner', name: 'organization_transfer_ownership', requirements: ['accountId' => Requirements::UUID], methods: ['PUT'])]
#[OA\Put(
    description: 'Transfer organization ownership to another employee. Only the current owner may do this; the target becomes owner and the requester becomes a regular member. The owner cannot transfer ownership to themselves.',
    summary: 'Transfer Ownership',
    tags: ['Organization'],
    parameters: [
        new OA\Parameter(name: 'accountId', description: 'Employee account ID to make the new owner', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Ownership transferred'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Only the organization owner can transfer ownership, or the owner tried to transfer to themselves'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'No such employee in this organization'),
    ]
)]
final readonly class ChangeEmployeeRoleController
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
        $handledStamp = $this->bus->dispatch(new ChangeEmployeeRoleCommand(
            requestingAccountId: $user->getUserId()->asString(),
            targetAccountId: $accountId,
        ))->last(HandledStamp::class);

        /** @var TransferredOwnership $result */
        $result = $handledStamp?->getResult();

        return new JsonResponse([
            'accountId' => $result->newOwnerAccountId,
            'email' => $result->newOwnerEmail,
            'role' => 'owner',
        ], Response::HTTP_OK);
    }
}
