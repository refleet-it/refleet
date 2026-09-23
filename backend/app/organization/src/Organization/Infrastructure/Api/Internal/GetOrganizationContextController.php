<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Api\Internal;

use App\Shared\Domain\Exception\AccountHasNoOrganizationException;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\UserId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Backs App\Shared\Infrastructure\Http\Internal\HttpOrganizationContextProvider, the
 * adapter every other context uses for OrganizationContextProviderInterface — see
 * docs/adr/0001-multiple-kernels.md. Reports "not found" as a 200 with found:false rather
 * than a 4xx, so the adapter can distinguish "no organization" (a normal outcome the
 * interface's own contract already models) from a transport failure.
 */
#[Route('/organization-context/{accountId}', name: 'internal_organization_context', methods: ['GET'])]
final readonly class GetOrganizationContextController
{
    public function __construct(
        private OrganizationContextProviderInterface $organizationContextProvider,
    ) {
    }

    public function __invoke(string $accountId): JsonResponse
    {
        try {
            $context = $this->organizationContextProvider->requireForAccount(UserId::fromString($accountId));
        } catch (AccountHasNoOrganizationException) {
            return new JsonResponse(['found' => false]);
        }

        return new JsonResponse([
            'found' => true,
            'id' => $context->id,
            'name' => $context->name,
            'role' => $context->role,
        ]);
    }
}
