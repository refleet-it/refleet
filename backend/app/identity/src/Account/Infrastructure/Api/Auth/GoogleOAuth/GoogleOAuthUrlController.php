<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\GoogleOAuth;

use App\Identity\Account\Infrastructure\Service\GoogleOAuthClient;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/identity/auth/google/url', name: 'auth_google_url', methods: ['GET'])]
#[OA\Get(
    description: 'Get Google OAuth authorization URL',
    summary: 'Get Google OAuth URL',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Authorization URL generated'),
    ]
)]
final readonly class GoogleOAuthUrlController
{
    private const string ENV_GOOGLE_REDIRECT_URI = 'GOOGLE_OAUTH_REDIRECT_URI';

    private const int STATE_RANDOM_BYTES = 16;

    public function __invoke(
        Request $request,
        GoogleOAuthClient $googleClient,
    ): JsonResponse {
        $defaultRedirectUri = $_ENV[self::ENV_GOOGLE_REDIRECT_URI] ?? '';
        \assert(\is_string($defaultRedirectUri));
        $redirectUri = $request->query->get('redirect_uri', $defaultRedirectUri);

        $state = \bin2hex(\random_bytes(self::STATE_RANDOM_BYTES));

        $authUrl = $googleClient->getAuthorizationUrl($redirectUri, $state);

        return new JsonResponse([
            'url' => $authUrl,
            'state' => $state,
        ], Response::HTTP_OK);
    }
}
