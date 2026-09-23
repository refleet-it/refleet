<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\CliAuthorization\Start;

use App\Identity\Account\Application\Command\StartCliAuthorization\StartCliAuthorizationCommand;
use App\Identity\Account\Application\Command\StartCliAuthorization\StartedCliAuthorization;
use App\Shared\Infrastructure\Security\RateLimiter;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/identity/cli-authorizations', name: 'cli_authorization_start', methods: ['POST'])]
#[OA\Post(
    description: 'Start a browser-approved CLI login (`refleet login`). Public: the CLI has no credentials yet. Returns the URL to open and the device secret to poll with.',
    summary: 'Start CLI authorization',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_CREATED, description: 'Authorization started'),
        new OA\Response(response: Response::HTTP_TOO_MANY_REQUESTS, description: 'Too many authorizations started from this address'),
    ]
)]
final readonly class StartCliAuthorizationController
{
    public const string VERIFICATION_PATH = '/cli/authorize/';

    public const int POLL_INTERVAL_SECONDS = 3;

    /** Unauthenticated and each call writes a row, so the only brake on filling the table is per address. */
    private const int MAX_STARTS = 10;

    private const int WINDOW_SECONDS = 600;

    public function __construct(
        private MessageBusInterface $bus,
        private RateLimiter $rateLimiter,
        #[Autowire('%env(FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        StartCliAuthorizationRequest $payload,
        Request $request,
    ): JsonResponse {
        $this->rateLimiter->throttle(
            RateLimiter::keyForIp('cli_authorization_start', $request->getClientIp() ?? 'unknown'),
            self::MAX_STARTS,
            self::WINDOW_SECONDS,
        );

        $handledStamp = $this->bus->dispatch(new StartCliAuthorizationCommand($payload->runnerName))->last(HandledStamp::class);

        /** @var StartedCliAuthorization $started */
        $started = $handledStamp?->getResult();

        return new JsonResponse([
            'userCode' => $started->userCode,
            'deviceSecret' => $started->deviceSecret,
            'verificationUrl' => \rtrim($this->frontendUrl, '/').self::VERIFICATION_PATH.$started->userCode,
            'expiresAt' => $started->expiresAt,
            'pollIntervalSeconds' => self::POLL_INTERVAL_SECONDS,
        ], Response::HTTP_CREATED);
    }
}
