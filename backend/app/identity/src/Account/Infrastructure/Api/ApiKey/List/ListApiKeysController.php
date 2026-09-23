<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\ApiKey\List;

use App\Identity\Account\Application\Query\ApiKeyReadModel;
use App\Identity\Account\Application\Query\ListApiKeys\ListApiKeysQuery;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/identity/api-keys', name: 'api_key_list', methods: ['GET'])]
#[OA\Get(
    description: 'List API keys belonging to the current account. Never includes the secret.',
    summary: 'List API Keys',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'List of API keys'),
    ]
)]
final readonly class ListApiKeysController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $handledStamp = $this->bus->dispatch(new ListApiKeysQuery($user->getUserId()->asString()))->last(HandledStamp::class);

        /** @var ApiKeyReadModel[] $apiKeys */
        $apiKeys = $handledStamp?->getResult() ?? [];

        return new JsonResponse([
            'apiKeys' => \array_map(static fn (ApiKeyReadModel $apiKey): array => [
                'id' => $apiKey->id,
                'name' => $apiKey->name,
                'prefix' => $apiKey->prefix,
                'createdAt' => $apiKey->createdAt,
                'lastUsedAt' => $apiKey->lastUsedAt,
                'revokedAt' => $apiKey->revokedAt,
            ], $apiKeys),
            'count' => \count($apiKeys),
        ], Response::HTTP_OK);
    }
}
