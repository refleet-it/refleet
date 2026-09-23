<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\CliAuthorization\Deny;

use App\Identity\Account\Application\Command\DenyCliAuthorization\DenyCliAuthorizationCommand;
use App\Identity\Account\Infrastructure\Api\CliAuthorization\UserCode;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/identity/cli-authorizations/{userCode}/deny', name: 'cli_authorization_deny', requirements: ['userCode' => UserCode::REQUIREMENT], methods: ['POST'])]
#[OA\Post(
    description: 'Deny a CLI login. The waiting CLI is told on its next poll and stops.',
    summary: 'Deny CLI authorization',
    tags: ['Identity Account'],
    parameters: [
        new OA\Parameter(name: 'userCode', description: 'Code from the verification URL', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Denied'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Unknown code'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Already decided'),
        new OA\Response(response: Response::HTTP_GONE, description: 'Expired'),
    ]
)]
final readonly class DenyCliAuthorizationController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(string $userCode): JsonResponse
    {
        $this->bus->dispatch(new DenyCliAuthorizationCommand($userCode));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
