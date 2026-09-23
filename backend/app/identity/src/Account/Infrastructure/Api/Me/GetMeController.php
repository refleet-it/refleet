<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Me;

use App\Identity\Account\Domain\Account\Exception\AccountNotFoundException;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Infrastructure\Api\AccountReadModel;
use App\Shared\Domain\User\AccountUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/account/me', name: 'account_me', methods: ['GET'])]
#[OA\Get(
    description: 'Get the current authenticated account.',
    summary: 'Get Current Account',
    tags: ['Identity Account'],
    responses: [
        new OA\Response(response: Response::HTTP_OK, description: 'Current account'),
        new OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Unauthorized'),
    ]
)]
final readonly class GetMeController
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        AccountUser $user,
    ): JsonResponse {
        $account = $this->accountRepository->findById(AccountId::fromString($user->getUserId()->asString()));

        if (null === $account) {
            throw new AccountNotFoundException();
        }

        $readModel = new AccountReadModel(
            id: $account->id()->asString(),
            email: $account->email(),
            role: $account->role()->value,
            status: $account->status()->value,
            createdAt: $account->createdAt()->format('c'),
            updatedAt: $account->updatedAt()->format('c'),
        );

        return new JsonResponse([
            'id' => $readModel->id,
            'email' => $readModel->email,
            'role' => $readModel->role,
            'status' => $readModel->status,
        ], Response::HTTP_OK);
    }
}
