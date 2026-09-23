<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\ResetPassword;

use App\Identity\Account\Application\Command\ResetPassword\ResetPasswordCommand;
use App\Shared\Infrastructure\Security\RateLimiter;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/identity/reset-password', name: 'auth_reset_password', methods: ['POST'])]
#[OA\Post(
    description: 'Reset password using a valid reset token',
    summary: 'Reset password',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Password reset successful'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Reset token not found'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Token expired, already used, or validation errors'),
        new OA\Response(response: Response::HTTP_TOO_MANY_REQUESTS, description: 'Too many attempts'),
    ]
)]
final readonly class ResetPasswordController
{
    private const int MAX_ATTEMPTS = 10;

    private const int WINDOW_SECONDS = 60;

    public function __construct(
        private RateLimiter $rateLimiter,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        ResetPasswordRequest $payload,
        MessageBusInterface $bus,
        Request $request,
    ): JsonResponse {
        $this->rateLimiter->throttle(
            RateLimiter::keyForIp('reset_password', $request->getClientIp() ?? 'unknown'),
            self::MAX_ATTEMPTS,
            self::WINDOW_SECONDS,
        );

        $bus->dispatch(new ResetPasswordCommand(
            token: $payload->token,
            newPassword: $payload->newPassword
        ));

        return new JsonResponse([
            'message' => 'Password has been successfully reset.',
        ], Response::HTTP_OK);
    }
}
