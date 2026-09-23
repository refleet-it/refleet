<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Auth\VerifyEmail;

use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Application\Command\VerifyEmail\VerifyEmailCommand;
use App\Identity\Account\Infrastructure\Factory\RefreshTokenCookieFactory;
use App\Shared\Infrastructure\Security\RateLimiter;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/identity/verify-email/{token}', name: 'auth_verify_email', methods: ['POST'])]
#[OA\Post(
    description: "Verify email address using the verification token sent to the user's email.",
    summary: 'Verify email address',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Email verified successfully, returns authentication tokens'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Verification token not found'),
        new OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Token expired or already used'),
        new OA\Response(response: Response::HTTP_TOO_MANY_REQUESTS, description: 'Too many verification attempts'),
    ]
)]
final readonly class VerifyEmailController
{
    private const int MAX_ATTEMPTS = 10;

    private const int WINDOW_SECONDS = 60;

    public function __construct(
        private RefreshTokenCookieFactory $cookieFactory,
        private RateLimiter $rateLimiter,
    ) {
    }

    public function __invoke(
        string $token,
        MessageBusInterface $bus,
        Request $request,
    ): JsonResponse {
        $this->rateLimiter->throttle(
            RateLimiter::keyForIp('verify_email', $request->getClientIp() ?? 'unknown'),
            self::MAX_ATTEMPTS,
            self::WINDOW_SECONDS,
        );

        $handledStamp = $bus->dispatch(new VerifyEmailCommand($token))->last(HandledStamp::class);

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

        return $response;
    }
}
