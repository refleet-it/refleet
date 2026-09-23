<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Internal;

use App\Shared\Domain\Service\InvitedAccountRegistrarInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Backs App\Shared\Infrastructure\Http\Internal\HttpInvitedAccountRegistrar, the adapter
 * Organization uses for InvitedAccountRegistrarInterface — see
 * docs/adr/0001-multiple-kernels.md.
 *
 * Known simplification: registerFromInvitation() can throw a specific domain exception
 * (e.g. the email is already registered). That propagates through Symfony's normal
 * exception handling here, but InternalApiClient's caller only ever sees the generic
 * InternalApiCallFailedException — the specific type and message don't cross the wire.
 * AcceptInvitationHandler doesn't currently catch anything from this call, so nothing
 * relies on that specificity today, but a caller that wants to distinguish "already
 * registered" from "call failed" would need this endpoint to report it in the response
 * body (200/409 with a reason) instead of raising, and the adapter updated to match.
 */
#[Route('/accounts/register-from-invitation', name: 'internal_register_invited_account', methods: ['POST'])]
final readonly class RegisterInvitedAccountController
{
    public function __construct(
        private InvitedAccountRegistrarInterface $accountRegistrar,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{email: string, plainPassword: string} $payload */
        $payload = \json_decode($request->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $accountId = $this->accountRegistrar->registerFromInvitation($payload['email'], $payload['plainPassword']);

        return new JsonResponse(['accountId' => $accountId]);
    }
}
