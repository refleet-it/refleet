<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Api\Internal;

use App\Shared\Domain\Service\ApiKeyValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Backs App\Shared\Infrastructure\Http\Internal\HttpApiKeyValidator, the adapter every
 * context but Identity uses for ApiKeyValidatorInterface — see
 * docs/adr/0001-multiple-kernels.md. Reachable only through the `internal` firewall
 * (X-Internal-Token), never the public one.
 */
#[Route('/api-keys/validate', name: 'internal_api_keys_validate', methods: ['POST'])]
final readonly class ValidateApiKeyController
{
    public function __construct(
        private ApiKeyValidatorInterface $apiKeyValidator,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = \json_decode($request->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $plainToken = $payload['token'] ?? null;

        if (!\is_string($plainToken) || '' === $plainToken) {
            return new JsonResponse(['valid' => false], Response::HTTP_BAD_REQUEST);
        }

        $validated = $this->apiKeyValidator->validate($plainToken);

        if (null === $validated) {
            return new JsonResponse(['valid' => false]);
        }

        return new JsonResponse([
            'valid' => true,
            'apiKeyId' => $validated->apiKeyId,
            'accountId' => $validated->accountId,
            'email' => $validated->email,
            'symfonyRoles' => $validated->symfonyRoles,
        ]);
    }
}
