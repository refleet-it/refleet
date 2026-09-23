<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\Logout;

use App\Identity\Account\Infrastructure\Factory\AccessTokenCookieFactory;
use App\Identity\Account\Infrastructure\Factory\RefreshTokenCookieFactory;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/identity/logout', name: 'auth_logout', methods: ['POST'])]
#[OA\Post(
    description: 'Logout user and clear refresh token cookie',
    summary: 'Logout',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Successfully logged out'),
    ]
)]
final readonly class LogoutController
{
    public function __construct(
        private RefreshTokenCookieFactory $cookieFactory,
        private AccessTokenCookieFactory $accessTokenCookieFactory,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $response = new JsonResponse(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->cookieFactory->createClear());
        $response->headers->setCookie($this->accessTokenCookieFactory->createClear());

        return $response;
    }
}
