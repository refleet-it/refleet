<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\CancelInvitation;

use App\Organization\Organization\Application\Command\CancelInvitation\CancelInvitationCommand;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/organizations/invitations/{invitationId}', name: 'organization_cancel_invitation', requirements: ['invitationId' => Requirements::UUID], methods: ['DELETE'])]
#[OA\Delete(
    description: "Cancel a pending invitation for the current account's organization. Only the organization owner may do this.",
    summary: 'Cancel Invitation',
    tags: ['Organization'],
    parameters: [
        new OA\Parameter(name: 'invitationId', description: 'Invitation ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Invitation cancelled'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Only the organization owner can cancel invitations'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'No such pending invitation in this organization'),
        new OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invitation is no longer pending'),
    ]
)]
final readonly class CancelInvitationController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        string $invitationId,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $this->bus->dispatch(new CancelInvitationCommand(
            requestingAccountId: $user->getUserId()->asString(),
            invitationId: $invitationId,
        ));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
