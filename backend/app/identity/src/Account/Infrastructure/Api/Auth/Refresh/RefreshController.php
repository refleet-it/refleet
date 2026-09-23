<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\Refresh;

use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Infrastructure\Factory\AccessTokenCookieFactory;
use App\Identity\Account\Infrastructure\Factory\RefreshTokenCookieFactory;
use App\Identity\RefreshToken\Application\Command\Refresh\RefreshCommand;
use App\Shared\Infrastructure\Security\RateLimiter;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/identity/refresh', name: 'auth_refresh', methods: ['POST'])]
#[OA\Post(
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Refreshed'),
        new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Missing or invalid refresh token cookie'),
        new OA\Response(response: Response::HTTP_TOO_MANY_REQUESTS, description: 'Too many refresh attempts'),
    ]
)]
final readonly class RefreshController
{
    private const int MAX_ATTEMPTS = 20;

    private const int WINDOW_SECONDS = 60;

    public function __construct(
        private RefreshTokenCookieFactory $cookieFactory,
        private AccessTokenCookieFactory $accessTokenCookieFactory,
        private RateLimiter $rateLimiter,
    ) {
    }

    public function __invoke(
        Request $request,
        MessageBusInterface $bus,
    ): JsonResponse {
        $this->rateLimiter->throttle(
            RateLimiter::keyForIp('refresh', $request->getClientIp() ?? 'unknown'),
            self::MAX_ATTEMPTS,
            self::WINDOW_SECONDS,
        );

        $refreshToken = $request->cookies->get(RefreshTokenCookieFactory::COOKIE_NAME);

        if (!\is_string($refreshToken) || '' === $refreshToken) {
            return new JsonResponse(
                ['error' => 'Missing refresh token cookie'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $handledStamp = $bus->dispatch(new RefreshCommand(
            refreshToken: $refreshToken,
        ))->last(HandledStamp::class);

        /** @var TokensDto|null $tokensDto */
        $tokensDto = $handledStamp?->getResult();

        $response = new JsonResponse([
            'token' => [
                'jwtToken' => null !== $tokensDto ? $tokensDto->jwtToken : '',
            ],
        ], Response::HTTP_OK);

        if (null !== $tokensDto && null !== $tokensDto->refreshToken) {
            $response->headers->setCookie($this->cookieFactory->create($tokensDto->refreshToken));
        }

        if (null !== $tokensDto) {
            $response->headers->setCookie($this->accessTokenCookieFactory->create($tokensDto->jwtToken));
        }

        return $response;
    }
}
