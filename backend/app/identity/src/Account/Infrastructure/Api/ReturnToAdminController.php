<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api;

use App\Identity\Account\Application\Command\ReturnToAdmin\ReturnToAdminCommand;
use App\Identity\Account\Application\Command\TokensDto;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Infrastructure\Factory\RefreshTokenCookieFactory;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/identity/return-to-admin', name: 'identity_return_to_admin', methods: ['POST'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[OA\Post(
    description: 'End impersonation session and return to admin account',
    summary: 'Return to Admin',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Returned to admin successfully'),
        new OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not in an impersonation session'),
        new OA\Response(response: Response::HTTP_NOT_FOUND, description: 'Admin account not found'),
    ]
)]
final readonly class ReturnToAdminController
{
    public function __construct(
        private MessageBusInterface $bus,
        private RefreshTokenCookieFactory $cookieFactory,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $impersonatorId = $user->getImpersonatorId();

        if (null === $impersonatorId) {
            return new JsonResponse(['error' => 'Not in an impersonation session'], Response::HTTP_FORBIDDEN);
        }

        $command = new ReturnToAdminCommand(
            adminAccountId: AccountId::fromString($impersonatorId),
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
