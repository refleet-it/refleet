<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Instance;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Which external sign-in providers this instance has been configured with.
 *
 * The sign-in screen cannot work this out on its own: one browser bundle serves every
 * deployment, so a button for a provider nobody configured would be offered everywhere and
 * fail everywhere. Configured means "has the credentials to complete a flow" — the same test
 * each provider's client makes before it will start one.
 *
 * Reading the environment here rather than asking the identity context keeps this in Shared,
 * where InstanceController lives: every application boots this controller, and only one of
 * them has an identity context to ask.
 */
final readonly class ConfiguredAuthProviders
{
    public function __construct(
        #[Autowire('%env(GOOGLE_CLIENT_ID)%')]
        private string $googleClientId,
        #[Autowire('%env(GOOGLE_CLIENT_SECRET)%')]
        private string $googleClientSecret,
        #[Autowire('%env(OIDC_ISSUER_URL)%')]
        private string $oidcIssuer,
        #[Autowire('%env(OIDC_CLIENT_ID)%')]
        private string $oidcClientId,
        #[Autowire('%env(OIDC_CLIENT_SECRET)%')]
        private string $oidcClientSecret,
        #[Autowire('%env(OIDC_PROVIDER_LABEL)%')]
        private string $oidcLabel,
    ) {
    }

    /**
     * @return list<array{provider: string, label: string}>
     */
    public function toArray(): array
    {
        $providers = [];

        if ('' !== $this->googleClientId && '' !== $this->googleClientSecret) {
            // Google is Google everywhere, so it has no configurable name; an empty label
            // means the frontend uses its own, which is also how it stays translated.
            $providers[] = ['provider' => 'google', 'label' => ''];
        }

        if ('' !== $this->oidcIssuer && '' !== $this->oidcClientId && '' !== $this->oidcClientSecret) {
            // An OIDC issuer does have a name worth showing: operators run Dex or Keycloak
            // under their own company's name far more often than under "OIDC".
            $providers[] = ['provider' => 'oidc', 'label' => $this->oidcLabel];
        }

        return $providers;
    }
}
