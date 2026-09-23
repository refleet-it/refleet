<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api;

use App\Identity\Account\Application\Command\Impersonate\ImpersonateCommand;
use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Infrastructure\Factory\RefreshTokenCookieFactory;
use App\Shared\Domain\User\AccountUser;
use App\Shared\Infrastructure\Http\Routing\Requirements;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/identity/impersonate/{targetAccountId}', name: 'identity_impersonate', requirements: ['targetAccountId' => Requirements::UUID], methods: ['POST'], priority: 100)]
#[IsGranted('ROLE_ADMINISTRATOR')]
#[OA\Post(
    description: 'Get login data for impersonating another user',
    summary: 'Impersonate User',
    tags: ['Identity Account'],
    parameters: [
        new OA\Parameter(name: 'targetAccountId', description: 'ID of the account to impersonate', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(
            response: Response::HTTP_OK,
            description: 'Login data returned successfully',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(schema: 'TokensDto')
            )
        ),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Account not found'),
        new OA\Response(response: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Impersonation not allowed'),
    ]
)]
final readonly class ImpersonationController
{
    public function __construct(
        private MessageBusInterface $bus,
        private RefreshTokenCookieFactory $cookieFactory,
    ) {
    }

    public function __invoke(
        string $targetAccountId,
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $command = new ImpersonateCommand(
            adminAccountId: AccountId::fromString($user->getUserId()->asString()),
            targetAccountId: AccountId::fromString($targetAccountId),
        );

        $handledStamp = $this->bus->dispatch($command)->last(HandledStamp::class);

        if (null === $handledStamp) {
            throw new \RuntimeException('Command not handled');
        }

        /** @var TokensDto $result */
        $result = $handledStamp->getResult();

        $response = new JsonResponse([
            'token' => $result->jwtToken,
        ], Response::HTTP_OK);

        if (null !== $result->refreshToken) {
            $response->headers->setCookie($this->cookieFactory->create($result->refreshToken));
        }

        return $response;
    }
}
