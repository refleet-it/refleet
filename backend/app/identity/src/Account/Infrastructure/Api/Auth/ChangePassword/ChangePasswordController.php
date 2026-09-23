<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\ChangePassword;

use App\Identity\Account\Application\Command\ChangePassword\ChangePasswordCommand;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/identity/change-password', name: 'auth_change_password', methods: ['POST'])]
#[OA\Post(
    description: 'Change the password of the currently authenticated account. Requires the current password.',
    summary: 'Change password',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Password changed successfully'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Current password is incorrect or validation errors'),
    ]
)]
final readonly class ChangePasswordController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        ChangePasswordRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $this->bus->dispatch(new ChangePasswordCommand(
            accountId: $user->getUserId()->asString(),
            currentPassword: $payload->currentPassword,
            newPassword: $payload->newPassword,
        ));

        return new JsonResponse([
            'message' => 'Password changed successfully.',
        ], Response::HTTP_OK);
    }
}
