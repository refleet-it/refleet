<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\ApiKey\Create;

use App\Identity\Account\Application\Command\CreateApiKey\CreateApiKeyCommand;
use App\Identity\Account\Application\Command\CreateApiKey\CreatedApiKey;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/identity/api-keys', name: 'api_key_create', methods: ['POST'])]
#[OA\Post(
    description: 'Create a new long-lived API key for the current account. The plaintext token is returned exactly once.',
    summary: 'Create API Key',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_CREATED, description: 'API key created'),
    ]
)]
final readonly class CreateApiKeyController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        #[MapRequestPayload]
        CreateApiKeyRequest $payload,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $handledStamp = $this->bus->dispatch(new CreateApiKeyCommand(
            accountId: $user->getUserId()->asString(),
            name: $payload->name,
        ))->last(HandledStamp::class);

        /** @var CreatedApiKey $created */
        $created = $handledStamp?->getResult();

        return new JsonResponse([
            'id' => $created->id,
            'name' => $created->name,
            'prefix' => $created->prefix,
            'token' => $created->token,
            'createdAt' => $created->createdAt,
        ], Response::HTTP_CREATED);
    }
}
