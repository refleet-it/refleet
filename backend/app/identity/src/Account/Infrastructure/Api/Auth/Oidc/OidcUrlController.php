<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\Oidc;

use App\Identity\Account\Domain\Account\Exception\OidcNotConfiguredException;
use App\Identity\Account\Domain\Account\Service\OidcClientInterface;
use App\Identity\Account\Infrastructure\Factory\OidcFlowCookieFactory;
use App\Identity\Account\Infrastructure\Security\OidcStateSigner;
use App\Shared\Infrastructure\Security\RateLimiter;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Starts a single sign-on: returns where to send the browser, and remembers in a cookie that
 * this browser is the one that asked.
 *
 * The redirect URI is not a parameter. Letting a caller choose it would make this endpoint a
 * way to have the provider send an authorization code to an address of the caller's choosing;
 * it is derived from the instance's own frontend URL instead.
 */
#[Route('/identity/auth/oidc/url', name: 'auth_oidc_url', methods: ['GET'])]
#[OA\Get(
    description: "Begin signing in through this instance's OIDC provider: returns the authorization URL to send the browser to.",
    summary: 'Get OIDC authorization URL',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Authorization URL'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'This instance has no OIDC provider configured'),
        new OA\Response(response: Response::HTTP_TOO_MANY_REQUESTS, description: 'Too many attempts'),
    ]
)]
final readonly class OidcUrlController
{
    private const int MAX_ATTEMPTS = 20;

    private const int WINDOW_SECONDS = 60;

    public function __construct(
        private OidcClientInterface $oidcClient,
        private OidcStateSigner $stateSigner,
        private OidcFlowCookieFactory $flowCookieFactory,
        private RateLimiter $rateLimiter,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->rateLimiter->throttle(
            RateLimiter::keyForIp('oidc_url', $request->getClientIp() ?? 'unknown'),
            self::MAX_ATTEMPTS,
            self::WINDOW_SECONDS,
        );

        if (!$this->oidcClient->isConfigured()) {
            throw new OidcNotConfiguredException();
        }

        $nonce = $this->stateSigner->newNonce();
        $state = $this->stateSigner->sign($nonce);

        $response = new JsonResponse([
            'url' => $this->oidcClient->authorizationUrl($state, $nonce),
        ], Response::HTTP_OK);

        $response->headers->setCookie($this->flowCookieFactory->create($state));

        return $response;
    }
}
