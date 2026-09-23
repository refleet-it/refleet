<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\CliAuthorization\Get;

use App\Identity\Account\Application\Query\GetCliAuthorization\CliAuthorizationReadModel;
use App\Identity\Account\Application\Query\GetCliAuthorization\GetCliAuthorizationQuery;
use App\Identity\Account\Infrastructure\Api\CliAuthorization\UserCode;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/identity/cli-authorizations/{userCode}', name: 'cli_authorization_get', requirements: ['userCode' => UserCode::REQUIREMENT], methods: ['GET'])]
#[OA\Get(
    description: 'What the browser shows before asking the signed-in account to approve or deny a CLI login.',
    summary: 'Get CLI authorization',
    tags: ['Identity Account'],
    parameters: [
        new OA\Parameter(name: 'userCode', description: 'Code from the verification URL', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Authorization details'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Unknown code'),
        new OA\Response(response: Response::HTTP_GONE, description: 'Expired'),
    ]
)]
final readonly class GetCliAuthorizationController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(string $userCode): JsonResponse
    {
        $handledStamp = $this->bus->dispatch(new GetCliAuthorizationQuery($userCode))->last(HandledStamp::class);

        /** @var CliAuthorizationReadModel $authorization */
        $authorization = $handledStamp?->getResult();

        return new JsonResponse([
            'runnerName' => $authorization->runnerName,
            'status' => $authorization->status,
            'createdAt' => $authorization->createdAt,
            'expiresAt' => $authorization->expiresAt,
        ]);
    }
}
