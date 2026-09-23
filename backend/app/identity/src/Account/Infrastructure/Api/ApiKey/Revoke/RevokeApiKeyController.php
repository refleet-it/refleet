<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\ApiKey\Revoke;

use App\Identity\Account\Application\Command\RevokeApiKey\RevokeApiKeyCommand;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/identity/api-keys/{apiKeyId}', name: 'api_key_revoke', requirements: ['apiKeyId' => Requirements::UUID], methods: ['DELETE'])]
#[OA\Delete(
    description: 'Revoke an API key. Revocation is permanent; a new key must be generated to replace it.',
    summary: 'Revoke API Key',
    tags: ['Identity Account'],
    parameters: [
        new OA\Parameter(name: 'apiKeyId', description: 'API Key ID', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'API key revoked'),
    ]
)]
final readonly class RevokeApiKeyController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        string $apiKeyId,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $this->bus->dispatch(new RevokeApiKeyCommand($user->getUserId()->asString(), $apiKeyId));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
