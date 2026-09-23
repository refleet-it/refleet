<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Internal;

use App\Shared\Domain\Service\InvitedAccountRegistrarInterface;
use App\Shared\Infrastructure\Http\InternalApiClient;

/**
 * InvitedAccountRegistrarInterface adapter for every context but Identity, which
 * implements it directly. See docs/adr/0001-multiple-kernels.md and the note on
 * Identity\...\Api\Internal\RegisterInvitedAccountController about exception fidelity
 * across this boundary.
 */
final readonly class HttpInvitedAccountRegistrar implements InvitedAccountRegistrarInterface
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
    public function registerFromInvitation(string $email, string $plainPassword): string
    {
        /** @var array{accountId: string} $data */
        $data = $this->client->post($this->identityInternalUrl, '/internal/accounts/register-from-invitation', [
            'email' => $email,
            'plainPassword' => $plainPassword,
        ]);

        return $data['accountId'];
    }
}
