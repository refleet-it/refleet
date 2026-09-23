<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\GoogleOAuth;

use App\Identity\Account\Application\Command\GoogleOAuthLogin\GoogleOAuthLoginCommand;
use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Infrastructure\Factory\AccessTokenCookieFactory;
use App\Identity\Account\Infrastructure\Factory\RefreshTokenCookieFactory;
use App\Identity\Account\Infrastructure\Service\GoogleOAuthClient;
use App\Shared\Domain\Exception\TooManyRequestsException;
use App\Shared\Infrastructure\Security\RateLimiter;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/identity/auth/google/callback', name: 'auth_google_callback', methods: ['POST'])]
#[OA\Post(
    description: 'Handle Google OAuth callback',
    summary: 'Google OAuth Callback',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Successfully authenticated'),
        new OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid OAuth data'),
        new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Authentication failed'),
        new OA\Response(response: Response::HTTP_TOO_MANY_REQUESTS, description: 'Too many callback attempts'),
    ]
)]
final readonly class GoogleOAuthCallbackController
{
    private const string ERROR_INVALID_BODY = 'Invalid request body';

    private const string ERROR_MISSING_ID_TOKEN = 'Missing idToken';

    private const string ERROR_AUTH_FAILED = 'Authentication failed';

    private const string ERROR_INTERNAL = 'Internal server error';

    private const int MAX_ATTEMPTS = 10;

    private const int WINDOW_SECONDS = 60;

    public function __construct(
        private GoogleOAuthClient $googleClient,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
        private RefreshTokenCookieFactory $cookieFactory,
        private AccessTokenCookieFactory $accessTokenCookieFactory,
        private RateLimiter $rateLimiter,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->rateLimiter->throttle(
                RateLimiter::keyForIp('google_callback', $request->getClientIp() ?? 'unknown'),
                self::MAX_ATTEMPTS,
                self::WINDOW_SECONDS,
            );

            return $this->handleRequest($request);
        } catch (TooManyRequestsException $e) {
            throw $e;
        } catch (\JsonException $e) {
            $this->logger->error('Invalid JSON in Google OAuth callback: '.$e->getMessage());

            return new JsonResponse(['error' => self::ERROR_INVALID_BODY], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            $this->logger->error('Google OAuth verification failed: '.$e->getMessage());

            return new JsonResponse(['error' => self::ERROR_AUTH_FAILED], Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error in Google OAuth callback: '.$e->getMessage());

            return new JsonResponse(['error' => self::ERROR_INTERNAL], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function handleRequest(Request $request): JsonResponse
    {
        $data = \json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($data)) {
            return new JsonResponse(['error' => self::ERROR_INVALID_BODY], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['idToken']) || !\is_string($data['idToken'])) {
            return new JsonResponse(['error' => self::ERROR_MISSING_ID_TOKEN], Response::HTTP_BAD_REQUEST);
        }

        $userData = $this->googleClient->verifyIdToken($data['idToken']);
        $this->logger->info('Google OAuth token verified for email: '.$userData['email']);

        $marketingConsent = isset($data['marketingConsent']) && true === $data['marketingConsent'];

        $handledStamp = $this->bus->dispatch(new GoogleOAuthLoginCommand(
            email: $userData['email'],
            googleId: $userData['sub'],
            marketingConsent: $marketingConsent,
        ))->last(HandledStamp::class);

        /** @var TokensDto|null $tokensDto */
        $tokensDto = $handledStamp?->getResult();

        return $this->buildTokenResponse($tokensDto);
    }

    private function buildTokenResponse(?TokensDto $tokensDto): JsonResponse
    {
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
