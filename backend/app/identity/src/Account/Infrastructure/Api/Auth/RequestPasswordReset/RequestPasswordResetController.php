<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\RequestPasswordReset;

use App\Identity\Account\Application\Command\RequestPasswordReset\RequestPasswordResetCommand;
use App\Shared\Domain\Exception\TooManyRequestsException;
use OpenApi\Attributes as OA;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/identity/request-password-reset', name: 'auth_request_password_reset', methods: ['POST'])]
#[OA\Post(
    description: 'Request a password reset for an account',
    summary: 'Request password reset',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Password reset email sent (if account exists)'),
        new OA\Response(response: Response::HTTP_TOO_MANY_REQUESTS, description: 'Too many requests'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation errors'),
    ]
)]
final readonly class RequestPasswordResetController
{
    private const int COOLDOWN_SECONDS = 60;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        RequestPasswordResetRequest $payload,
        MessageBusInterface $bus,
    ): JsonResponse {
        $cacheKey = 'pwd_reset_'.\hash('sha256', \strtolower($payload->email));
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            throw new TooManyRequestsException(self::COOLDOWN_SECONDS);
        }

        $bus->dispatch(new RequestPasswordResetCommand(
            email: $payload->email
        ));

        $item->set(true)->expiresAfter(self::COOLDOWN_SECONDS);
        $this->cache->save($item);

        return new JsonResponse([
            'message' => 'If an account with this email exists, a password reset link has been sent.',
        ], Response::HTTP_OK);
    }
}
