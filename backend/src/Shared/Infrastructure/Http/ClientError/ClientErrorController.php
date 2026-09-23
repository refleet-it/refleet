<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\ClientError;

use App\Shared\Infrastructure\Security\RateLimiter;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Receives unhandled client-side (browser) errors from the frontend's global Angular
 * ErrorHandler and feeds them into the same JSON-log -> Alloy -> Loki pipeline as
 * backend errors, so a production JS crash is no longer invisible. Public and
 * rate-limited: it must work even when the user isn't authenticated (e.g. an error on
 * the login page), and it must not become an unauthenticated log-flooding vector.
 */
#[Route('/client-errors', name: 'client_error_report', methods: ['POST'])]
#[OA\Post(
    description: 'Report an unhandled client-side error for observability',
    summary: 'Report client error',
    tags: ['Shared'],
    responses: [
        new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Error recorded'),
        new OA\Response(response: Response::HTTP_TOO_MANY_REQUESTS, description: 'Too many reports'),
    ]
)]
final readonly class ClientErrorController
{
    private const int MAX_ATTEMPTS = 20;

    private const int WINDOW_SECONDS = 60;

    public function __construct(
        private RateLimiter $rateLimiter,
        #[Autowire(service: 'monolog.logger.frontend')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        ClientErrorRequest $payload,
        Request $request,
    ): JsonResponse {
        $this->rateLimiter->throttle(
            RateLimiter::keyForIp('client_error', $request->getClientIp() ?? 'unknown'),
            self::MAX_ATTEMPTS,
            self::WINDOW_SECONDS,
        );

        $this->logger->error($payload->message, [
            'url' => $payload->url,
            'stack' => $payload->stack,
            'component_stack' => $payload->componentStack,
            'user_agent' => $request->headers->get('User-Agent'),
        ]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
