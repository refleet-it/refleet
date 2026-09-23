<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\AcceptInvitation;

use App\Organization\Organization\Application\Command\AcceptInvitation\AcceptedInvitation;
use App\Organization\Organization\Application\Command\AcceptInvitation\AcceptInvitationCommand;
use App\Shared\Infrastructure\Security\RateLimiter;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/organizations/invitations/accept', name: 'organization_accept_invitation', methods: ['POST'])]
#[OA\Post(
    description: 'Accept an organization invitation by token, creating the account and joining the organization. Public - the token itself is the credential.',
    summary: 'Accept Invitation',
    tags: ['Organization'],
    responses: [
        new OA\Response(response: Response::HTTP_CREATED, description: 'Invitation accepted, account created and joined the organization'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Invitation not found'),
        new OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invitation expired or already accepted'),
        new OA\Response(response: Response::HTTP_TOO_MANY_REQUESTS, description: 'Too many attempts'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation errors'),
    ]
)]
final readonly class AcceptInvitationController
{
    private const int MAX_ATTEMPTS = 10;

    private const int WINDOW_SECONDS = 60;

    public function __construct(
        private MessageBusInterface $bus,
        private RateLimiter $rateLimiter,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        AcceptInvitationRequest $payload,
        Request $request,
    ): JsonResponse {
        $this->rateLimiter->throttle(
            RateLimiter::keyForIp('accept_invitation', $request->getClientIp() ?? 'unknown'),
            self::MAX_ATTEMPTS,
            self::WINDOW_SECONDS,
        );

        $handledStamp = $this->bus->dispatch(new AcceptInvitationCommand(
            token: $payload->token,
            password: $payload->password,
        ))->last(HandledStamp::class);

        /** @var AcceptedInvitation $accepted */
        $accepted = $handledStamp?->getResult();

        return new JsonResponse([
            'email' => $accepted->email,
            'message' => 'Invitation accepted. You can now log in.',
        ], Response::HTTP_CREATED);
    }
}
