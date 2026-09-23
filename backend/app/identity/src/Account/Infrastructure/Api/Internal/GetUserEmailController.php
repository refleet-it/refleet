<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Internal;

use App\Shared\Domain\Service\UserEmailProviderInterface;
use App\Shared\Domain\User\UserId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Backs App\Shared\Infrastructure\Http\Internal\HttpUserEmailProvider, the adapter
 * Qualification and Shift use for UserEmailProviderInterface — see
 * docs/adr/0001-multiple-kernels.md.
 */
#[Route('/user-email/{userId}', name: 'internal_user_email', methods: ['GET'])]
final readonly class GetUserEmailController
{
    public function __construct(
        private UserEmailProviderInterface $userEmailProvider,
    ) {
    }

    public function __invoke(string $userId): JsonResponse
    {
        return new JsonResponse(['email' => $this->userEmailProvider->getEmailByUserId(UserId::fromString($userId))]);
    }
}
