<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\CliAuthorization\Claim;

use App\Identity\Account\Application\Command\ClaimCliAuthorization\ClaimCliAuthorizationCommand;
use App\Identity\Account\Application\Command\ClaimCliAuthorization\ClaimedCliAuthorization;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/identity/cli-authorizations/claim', name: 'cli_authorization_claim', methods: ['POST'])]
#[OA\Post(
    description: 'The CLI polls here with its device secret (in the body, never the URL). Answers pending/denied/expired, or approved together with a freshly minted API key — once.',
    summary: 'Claim CLI authorization',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Current outcome'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Unknown or already collected'),
    ]
)]
final readonly class ClaimCliAuthorizationController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        ClaimCliAuthorizationRequest $payload,
    ): JsonResponse {
        $handledStamp = $this->bus->dispatch(new ClaimCliAuthorizationCommand(
            deviceSecret: $payload->deviceSecret,
            apiKeyName: $payload->apiKeyName,
        ))->last(HandledStamp::class);

        /** @var ClaimedCliAuthorization $claimed */
        $claimed = $handledStamp?->getResult();

        $body = ['status' => $claimed->status];
        if (null !== $claimed->apiKey) {
            $body['accountEmail'] = $claimed->accountEmail;
            $body['apiKey'] = [
                'id' => $claimed->apiKey->id,
                'name' => $claimed->apiKey->name,
                'prefix' => $claimed->apiKey->prefix,
                'token' => $claimed->apiKey->token,
                'createdAt' => $claimed->apiKey->createdAt,
            ];
        }

        return new JsonResponse($body);
    }
}
