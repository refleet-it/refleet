<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Internal;

use App\Shared\Domain\Service\UserEmailProviderInterface;
use App\Shared\Domain\User\UserId;
use App\Shared\Infrastructure\Http\InternalApiClient;

/**
 * UserEmailProviderInterface adapter for every context but Identity, which implements it
 * directly. See docs/adr/0001-multiple-kernels.md.
 */
final readonly class HttpUserEmailProvider implements UserEmailProviderInterface
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
    public function getEmailByUserId(UserId $userId): ?string
    {
        $data = $this->client->get($this->identityInternalUrl, '/internal/user-email/'.$userId->asString());
        $email = $data['email'] ?? null;

        return \is_string($email) ? $email : null;
    }
}
