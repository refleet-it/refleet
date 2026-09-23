<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Internal;

use App\Shared\Domain\Service\ApiKeyValidatorInterface;
use App\Shared\Domain\ValueObject\ValidatedApiKey;
use App\Shared\Infrastructure\Http\InternalApiClient;

/**
 * ApiKeyValidatorInterface adapter for every context but Identity, which implements it
 * directly against its own database (see Identity\...\DoctrineApiKeyValidator). One class
 * serves every consuming context — the call is identical regardless of which context makes
 * it, so each app's own services.yaml just aliases the interface to this instead of
 * repeating the same client call under its own namespace. See
 * docs/adr/0001-multiple-kernels.md.
 */
final readonly class HttpApiKeyValidator implements ApiKeyValidatorInterface
{
    private string $identityInternalUrl;

    public function __construct(
        private InternalApiClient $client,
    ) {
        $url = $_ENV['IDENTITY_INTERNAL_URL'] ?? throw new \InvalidArgumentException('IDENTITY_INTERNAL_URL environment variable is not set');
        if (!\is_string($url) || '' === $url) {
            throw new \InvalidArgumentException('IDENTITY_INTERNAL_URL must be a non-empty string');
        }

        $this->identityInternalUrl = $url;
    }

    #[\Override]
    public function validate(string $plainToken): ?ValidatedApiKey
    {
        $response = $this->client->post($this->identityInternalUrl, '/internal/api-keys/validate', ['token' => $plainToken]);

        if (true !== ($response['valid'] ?? false)) {
            return null;
        }

        /** @var array{apiKeyId: string, accountId: string, email: string, symfonyRoles: string[]} $data */
        $data = $response;

        return new ValidatedApiKey(
            apiKeyId: $data['apiKeyId'],
            accountId: $data['accountId'],
            email: $data['email'],
            symfonyRoles: $data['symfonyRoles'],
        );
    }
}
