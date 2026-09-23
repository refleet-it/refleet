<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Internal;

use App\Shared\Domain\Exception\AccountHasNoOrganizationException;
use App\Shared\Domain\Service\OrganizationContextProviderInterface;
use App\Shared\Domain\User\UserId;
use App\Shared\Domain\ValueObject\OrganizationContext;
use App\Shared\Infrastructure\Http\InternalApiClient;

/**
 * OrganizationContextProviderInterface adapter for every context but Organization, which
 * implements it directly. One class serves every consuming context — see
 * docs/adr/0001-multiple-kernels.md.
 */
final readonly class HttpOrganizationContextProvider implements OrganizationContextProviderInterface
{
    private string $organizationInternalUrl;

    public function __construct(
        private InternalApiClient $client,
    ) {
        $url = $_ENV['ORGANIZATION_INTERNAL_URL'] ?? throw new \InvalidArgumentException('ORGANIZATION_INTERNAL_URL environment variable is not set');
        if (!\is_string($url) || '' === $url) {
            throw new \InvalidArgumentException('ORGANIZATION_INTERNAL_URL must be a non-empty string');
        }

        $this->organizationInternalUrl = $url;
    }

    #[\Override]
    public function requireForAccount(UserId $accountId): OrganizationContext
    {
        $response = $this->client->get($this->organizationInternalUrl, '/internal/organization-context/'.$accountId->asString());

        if (true !== ($response['found'] ?? false)) {
            throw new AccountHasNoOrganizationException();
        }

        /** @var array{id: string, name: string, role: string} $data */
        $data = $response;

        return new OrganizationContext(
            id: $data['id'],
            name: $data['name'],
            role: $data['role'],
        );
    }
}
