<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Service;

use App\Identity\Account\Domain\Account\Exception\OidcAuthenticationFailedException;
use App\Identity\Account\Domain\Account\Service\OidcClientInterface;
use App\Identity\Account\Domain\Account\ValueObject\OidcIdentity;
use App\Identity\Account\Infrastructure\Security\OidcIdTokenVerifier;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Authorization-code flow against a configured OIDC issuer, with discovery.
 *
 * Nothing about a particular provider is compiled in: the issuer URL is configuration, the
 * endpoints come from `/.well-known/openid-configuration`, and the signing keys come from
 * whatever `jwks_uri` that document names. Dex, Keycloak and Entra ID differ only in the
 * values, which is the point of doing it this way rather than per provider.
 */
final readonly class OidcClient implements OidcClientInterface
{
    /**
     * The callback the frontend serves: it reads `code` and `state` out of the query the
     * issuer redirected to and posts them to this API. The redirect URI has to be registered
     * at the provider, so it is derived from one setting rather than configured separately —
     * one fewer value an operator can get subtly wrong.
     */
    public const string CALLBACK_PATH = '/auth/oidc/callback';

    private const string DISCOVERY_PATH = '/.well-known/openid-configuration';

    /**
     * Long enough to keep a sign-in off the issuer's discovery endpoint, short enough that
     * repointing an instance does not need a cache flush. The signing keys have their own,
     * shorter life in OidcJwksProvider, because they rotate and these endpoints do not.
     */
    private const int DISCOVERY_CACHE_SECONDS = 3600;

    public function __construct(
        private OidcHttpReader $http,
        private OidcIdTokenVerifier $verifier,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        #[Autowire('%env(OIDC_ISSUER_URL)%')]
        private string $issuer,
        #[Autowire('%env(OIDC_CLIENT_ID)%')]
        private string $clientId,
        #[Autowire('%env(OIDC_CLIENT_SECRET)%')]
        private string $clientSecret,
        #[Autowire('%env(OIDC_SCOPES)%')]
        private string $scopes,
        #[Autowire('%env(FRONTEND_URL)%')]
        private string $frontendUrl,
    ) {
    }

    #[\Override]
    public function isConfigured(): bool
    {
        return '' !== $this->issuer && '' !== $this->clientId && '' !== $this->clientSecret;
    }

    #[\Override]
    public function authorizationUrl(string $state, string $nonce): string
    {
        $endpoint = $this->discover()['authorization_endpoint'];
        // Entra ID publishes an authorization endpoint with its tenant already in the query
        // string, so a bare "?" here would produce a URL with two of them.
        $separator = \str_contains($endpoint, '?') ? '&' : '?';

        return $endpoint.$separator.\http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => $this->scopes,
            'state' => $state,
            'nonce' => $nonce,
        ]);
    }

    #[\Override]
    public function exchangeCode(string $code, string $expectedNonce): OidcIdentity
    {
        $discovery = $this->discover();
        $idToken = $this->requestIdToken($discovery['token_endpoint'], $code);

        return $this->toIdentity($this->verifier->verify(
            idToken: $idToken,
            jwksUri: $discovery['jwks_uri'],
            issuer: $this->issuer,
            audience: $this->clientId,
            expectedNonce: $expectedNonce,
        ));
    }

    public function redirectUri(): string
    {
        return \rtrim($this->frontendUrl, '/').self::CALLBACK_PATH;
    }

    /**
     * @return array{authorization_endpoint: string, token_endpoint: string, jwks_uri: string}
     */
    private function discover(): array
    {
        if (!$this->isConfigured()) {
            throw new OidcAuthenticationFailedException('no OIDC issuer is configured on this instance');
        }

        $item = $this->cache->getItem('oidc_discovery_'.\hash('sha256', $this->issuer));
        $cached = $item->get();

        if ($item->isHit() && \is_array($cached)) {
            return $this->endpointsFrom(OidcHttpReader::asJsonObject($cached));
        }

        $document = $this->http->getJsonObject(
            \rtrim($this->issuer, '/').self::DISCOVERY_PATH,
            "the issuer's discovery document could not be read",
        );

        // An issuer that does not call itself what we call it is either a misconfiguration or a
        // document that is not the one we asked for; either way its endpoints are not ours to
        // trust, and the `iss` check on the token would fail afterwards anyway.
        $declaredIssuer = $document['issuer'] ?? null;

        if (!\is_string($declaredIssuer) || \rtrim($declaredIssuer, '/') !== \rtrim($this->issuer, '/')) {
            throw new OidcAuthenticationFailedException('the discovery document declares a different issuer than the one configured');
        }

        $discovery = $this->endpointsFrom($document);

        $this->cache->save($item->set($discovery)->expiresAfter(self::DISCOVERY_CACHE_SECONDS));

        return $discovery;
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array{authorization_endpoint: string, token_endpoint: string, jwks_uri: string}
     */
    private function endpointsFrom(array $document): array
    {
        return [
            'authorization_endpoint' => $this->requireHttpsUrl($document, 'authorization_endpoint'),
            'token_endpoint' => $this->requireHttpsUrl($document, 'token_endpoint'),
            'jwks_uri' => $this->requireHttpsUrl($document, 'jwks_uri'),
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function requireHttpsUrl(array $document, string $key): string
    {
        $value = $document[$key] ?? null;

        // http:// would put the code exchange and the signing keys on the wire in clear. The
        // one exception the spec allows is a localhost issuer, which this does not serve.
        if (!\is_string($value) || !\str_starts_with($value, 'https://')) {
            throw new OidcAuthenticationFailedException(\sprintf('the discovery document has no usable https %s', $key));
        }

        return $value;
    }

    private function requestIdToken(string $tokenEndpoint, string $code): string
    {
        $response = $this->http->postFormForJsonObject(
            $tokenEndpoint,
            [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
            'the issuer rejected the authorization code',
            'the issuer could not be reached for the token exchange',
        );

        $idToken = $response['id_token'] ?? null;

        if (!\is_string($idToken) || '' === $idToken) {
            throw new OidcAuthenticationFailedException('the issuer returned no id_token');
        }

        return $idToken;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function toIdentity(array $claims): OidcIdentity
    {
        $subject = $claims['sub'] ?? null;
        $email = $claims['email'] ?? null;
        $emailVerified = $claims['email_verified'] ?? null;
        $name = $claims['name'] ?? null;

        if (!\is_string($subject) || '' === $subject) {
            throw new OidcAuthenticationFailedException('the id_token carries no subject');
        }

        // An instance cannot place an account without an address, and it has nothing to match
        // an existing one on. Ask the operator for the `email` scope rather than inventing one.
        if (!\is_string($email) || '' === $email) {
            throw new OidcAuthenticationFailedException('the id_token carries no email address; check that the email scope is granted to this client');
        }

        // Taking an unverified address on trust is how one person signs in as another: claim
        // the victim's address at the issuer, and the match on it hands over their account.
        // AccountMergingPolicy trusts this provider to verify an address precisely because
        // this refuses the tokens where it did not.
        if (true !== $emailVerified) {
            throw new OidcAuthenticationFailedException('the issuer has not marked this email address as verified');
        }

        return new OidcIdentity(
            subject: $subject,
            email: $email,
            name: \is_string($name) ? $name : '',
        );
    }
}
