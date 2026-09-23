<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\AddEmployee;

use App\Organization\Organization\Application\Command\AddEmployee\AddedEmployee;
use App\Organization\Organization\Application\Command\AddEmployee\AddEmployeeCommand;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/organizations/employees', name: 'organization_add_employee', methods: ['POST'])]
#[OA\Post(
    description: "Add an already-registered account as an employee of the current account's organization. Only the organization owner may do this.",
    summary: 'Add Employee',
    tags: ['Organization'],
    responses: [
        new OA\Response(response: Response::HTTP_CREATED, description: 'Employee added'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Only the organization owner can add employees'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'No registered account with this email'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account already belongs to an organization'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation errors'),
    ]
)]
final readonly class AddEmployeeController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        AddEmployeeRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $handledStamp = $this->bus->dispatch(new AddEmployeeCommand(
            requestingAccountId: $user->getUserId()->asString(),
            email: $payload->email,
        ))->last(HandledStamp::class);

        /** @var AddedEmployee $added */
        $added = $handledStamp?->getResult();

        return new JsonResponse([
            'accountId' => $added->accountId,
            'email' => $added->email,
            'role' => $added->role,
        ], Response::HTTP_CREATED);
    }
}
