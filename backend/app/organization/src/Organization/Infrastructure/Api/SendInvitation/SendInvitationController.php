<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\SendInvitation;

use App\Organization\Organization\Application\Command\SendInvitation\SendInvitationCommand;
use App\Organization\Organization\Application\Command\SendInvitation\SentInvitation;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/organizations/invitations', name: 'organization_send_invitation', methods: ['POST'])]
#[OA\Post(
    description: "Invite someone to join the current account's organization by email. Only the organization owner may do this.",
    summary: 'Send Invitation',
    tags: ['Organization'],
    responses: [
        new OA\Response(response: Response::HTTP_CREATED, description: 'Invitation sent'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Only the organization owner can send invitations'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Account already in an organization, or invitation already pending'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation errors'),
    ]
)]
final readonly class SendInvitationController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        SendInvitationRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $handledStamp = $this->bus->dispatch(new SendInvitationCommand(
            requestingAccountId: $user->getUserId()->asString(),
            email: $payload->email,
        ))->last(HandledStamp::class);

        /** @var SentInvitation $sent */
        $sent = $handledStamp?->getResult();

        return new JsonResponse([
            'id' => $sent->id,
            'email' => $sent->email,
            'expiresAt' => $sent->expiresAt,
        ], Response::HTTP_CREATED);
    }
}
