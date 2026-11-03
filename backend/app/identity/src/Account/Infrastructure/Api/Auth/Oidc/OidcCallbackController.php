<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\Oidc;

use App\Identity\Account\Application\Command\OidcLogin\OidcLoginCommand;
use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\Exception\InvalidOidcStateException;
use App\Identity\Account\Domain\Account\Exception\OidcNotConfiguredException;
use App\Identity\Account\Domain\Account\Service\OidcClientInterface;
use App\Identity\Account\Infrastructure\Factory\AccessTokenCookieFactory;
use App\Identity\Account\Infrastructure\Factory\OidcFlowCookieFactory;
use App\Identity\Account\Infrastructure\Factory\RefreshTokenCookieFactory;
use App\Identity\Account\Infrastructure\Security\OidcStateSigner;
use App\Shared\Infrastructure\Security\RateLimiter;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Finishes a single sign-on: exchanges the code, verifies the id_token, and issues this
 * instance's own session on the strength of it.
 *
 * A POST rather than a GET the provider redirects to directly, because the frontend owns the
 * callback page: the browser lands there with `code` and `state` in the query, and the page
 * posts them here. Which also means the session cookies land on an ordinary same-site XHR
 * instead of a cross-site navigation.
 *
 * Exceptions deliberately propagate. A handler exception arrives wrapped in a
 * HandlerFailedException that UnwrapAppExceptionListener unwraps, so each one keeps the status
 * it declares — 403 when sign-up is closed, 422 for an inactive account, 409 for an identity
 * conflict — rather than being flattened into one generic failure by a catch here.
 */
#[Route('/identity/auth/oidc/callback', name: 'auth_oidc_callback', methods: ['POST'])]
#[OA\Post(
    description: "Complete signing in through this instance's OIDC provider.",
    summary: 'OIDC callback',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Successfully authenticated'),
        new OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'The sign-in request did not come from this browser, or took too long'),
        new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'The provider refused the code, or returned a token that does not verify'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'This instance does not accept self-registration'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'No OIDC provider configured, or the account is linked to a different identity at the provider'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Account is not active'),
        new OA\Response(response: Response::HTTP_TOO_MANY_REQUESTS, description: 'Too many callback attempts'),
    ]
)]
final readonly class OidcCallbackController
{
    private const int MAX_ATTEMPTS = 10;

    private const int WINDOW_SECONDS = 60;

    public function __construct(
        private OidcClientInterface $oidcClient,
        private OidcStateSigner $stateSigner,
        private OidcFlowCookieFactory $flowCookieFactory,
        private RefreshTokenCookieFactory $cookieFactory,
        private AccessTokenCookieFactory $accessTokenCookieFactory,
        private RateLimiter $rateLimiter,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        OidcCallbackRequest $payload,
        Request $request,
    ): JsonResponse {
        $this->rateLimiter->throttle(
            RateLimiter::keyForIp('oidc_callback', $request->getClientIp() ?? 'unknown'),
            self::MAX_ATTEMPTS,
            self::WINDOW_SECONDS,
        );

        if (!$this->oidcClient->isConfigured()) {
            throw new OidcNotConfiguredException();
        }

        $this->requireFlowStartedByThisBrowser($request, $payload->state);

        $nonce = $this->stateSigner->verify($payload->state);
        $identity = $this->oidcClient->exchangeCode($payload->code, $nonce);

        $this->logger->info('OIDC id_token verified for email: '.$identity->email);

        $handledStamp = $this->bus->dispatch(new OidcLoginCommand(
            email: $identity->email,
            subject: $identity->subject,
            marketingConsent: $payload->marketingConsent,
        ))->last(HandledStamp::class);

        /** @var TokensDto|null $tokensDto */
        $tokensDto = $handledStamp?->getResult();

        return $this->buildTokenResponse($tokensDto);
    }

    /**
     * The signature on `state` only proves this instance issued it — an attacker can obtain
     * one by starting a flow themselves, complete it, and hand the victim the resulting code
     * so the victim ends up in the attacker's account. The cookie is what the attacker cannot
     * produce, so the two have to agree.
     */
    private function requireFlowStartedByThisBrowser(Request $request, string $state): void
    {
        $cookie = $request->cookies->get(OidcFlowCookieFactory::COOKIE_NAME);

        if (!\is_string($cookie) || '' === $cookie || !\hash_equals($cookie, $state)) {
            throw new InvalidOidcStateException('this sign-in was not started in this browser');
        }
    }

    private function buildTokenResponse(?TokensDto $tokensDto): JsonResponse
    {
        $response = new JsonResponse([
            'token' => [
                'jwtToken' => $tokensDto->jwtToken ?? '',
            ],
        ], Response::HTTP_OK);

        if (null !== $tokensDto && null !== $tokensDto->refreshToken) {
            $response->headers->setCookie($this->cookieFactory->create($tokensDto->refreshToken));
        }

        if (null !== $tokensDto) {
            $response->headers->setCookie($this->accessTokenCookieFactory->create($tokensDto->jwtToken));
        }

        // One code is good for one sign-in, so the flow it belonged to is over either way.
        $response->headers->setCookie($this->flowCookieFactory->createClear());

        return $response;
    }
}
