<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\CliAuthorization\Approve;

use App\Identity\Account\Application\Command\ApproveCliAuthorization\ApproveCliAuthorizationCommand;
use App\Identity\Account\Infrastructure\Api\CliAuthorization\UserCode;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/identity/cli-authorizations/{userCode}/approve', name: 'cli_authorization_approve', requirements: ['userCode' => UserCode::REQUIREMENT], methods: ['POST'])]
#[OA\Post(
    description: 'Approve a CLI login as the current account. The CLI collects an API key for this account on its next poll.',
    summary: 'Approve CLI authorization',
    tags: ['Identity Account'],
    parameters: [
        new OA\Parameter(name: 'userCode', description: 'Code from the verification URL', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ],
    responses: [
        new OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Approved'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Unknown code'),
        new OA\Response(response: Response::HTTP_CONFLICT, description: 'Already decided'),
        new OA\Response(response: Response::HTTP_GONE, description: 'Expired'),
    ]
)]
final readonly class ApproveCliAuthorizationController
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(
        string $userCode,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $this->bus->dispatch(new ApproveCliAuthorizationCommand($user->getUserId()->asString(), $userCode));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
