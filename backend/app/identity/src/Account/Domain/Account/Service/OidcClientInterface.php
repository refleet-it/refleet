<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Service;

use App\Identity\Account\Domain\Account\ValueObject\OidcIdentity;

/**
 * Authorization-code flow against whichever OIDC issuer the instance is configured to trust —
 * Dex, Keycloak, Entra ID, anything that publishes a discovery document.
 *
 * Deliberately not a per-provider interface: everything provider-specific (issuer, client,
 * endpoints, signing keys) is either configuration or comes out of discovery, so a second
 * implementation would differ only in the parts that are already data.
 */
interface OidcClientInterface
{
    /**
     * Whether this instance has an issuer and a client to talk to it with. False is the
     * ordinary case: most instances configure no issuer, and the sign-in screen then offers
     * nothing rather than offering a button that cannot work.
     */
    public function isConfigured(): bool;

    /**
     * Where to send the browser to start the flow. The `nonce` is bound into the request here
     * and checked against the returned id_token in exchangeCode(), which is what makes a
     * replayed token useless.
     */
    public function authorizationUrl(string $state, string $nonce): string;

    /**
     * Exchanges the code for an id_token and verifies it: signature against the issuer's JWKS,
     * then `iss`, `aud`, `exp`, `iat` and `nonce`.
     *
     * @throws \App\Identity\Account\Domain\Account\Exception\OidcAuthenticationFailedException when the issuer cannot be reached, refuses the code, or returns a token that does not verify
     */
    public function exchangeCode(string $code, string $expectedNonce): OidcIdentity;
}
