<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Service;

use App\Identity\Account\Domain\Account\Exception\OidcAuthenticationFailedException;
use App\Identity\Account\Infrastructure\Security\OidcIdTokenVerifier;
use App\Identity\Account\Infrastructure\Security\OidcSignatureAlgorithms;
use App\Identity\Account\Infrastructure\Service\OidcClient;
use App\Identity\Account\Infrastructure\Service\OidcHttpReader;
use App\Identity\Account\Infrastructure\Service\OidcJwksProvider;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWS;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(OidcClient::class)]
#[UsesClass(OidcHttpReader::class)]
#[UsesClass(OidcJwksProvider::class)]
#[UsesClass(OidcIdTokenVerifier::class)]
#[UsesClass(OidcSignatureAlgorithms::class)]
#[UsesClass(OidcAuthenticationFailedException::class)]
final class OidcClientTest extends TestCase
{
    private const string ISSUER = 'https://sso.example.test';

    private const string CLIENT_ID = 'refleet';

    /**
     * 32 bytes, because the HMAC test below needs a secret HS256 will accept — a real client
     * secret is at least this long anyway.
     */
    private const string CLIENT_SECRET = 'client-secret-0123456789abcdefgh';

    private const string NONCE = 'the-nonce';

    private const string NOW = '2026-11-03 12:00:00';

    /**
     * Generating an RSA key is the slow part of this file, and every test wants the same one.
     */
    private static ?JWK $signingKey = null;

    private static ?JWK $rotatedKey = null;

    #[Test]
    public function is_configured_only_with_an_issuer_and_a_client(): void
    {
        Assert::assertTrue($this->client(new MockHttpClient())->isConfigured());
        Assert::assertFalse($this->client(new MockHttpClient(), issuer: '')->isConfigured());
        Assert::assertFalse($this->client(new MockHttpClient(), clientId: '')->isConfigured());
        Assert::assertFalse($this->client(new MockHttpClient(), clientSecret: '')->isConfigured());
    }

    #[Test]
    public function builds_the_authorization_url_from_the_discovered_endpoint(): void
    {
        // Act
        $url = $this->client($this->http())->authorizationUrl('signed-state', self::NONCE);

        // Assert
        Assert::assertStringStartsWith(self::ISSUER.'/auth?', $url);
        \parse_str((string) \parse_url($url, \PHP_URL_QUERY), $query);
        Assert::assertSame(self::CLIENT_ID, $query['client_id']);
        Assert::assertSame('http://localhost:4200/auth/oidc/callback', $query['redirect_uri']);
        Assert::assertSame('code', $query['response_type']);
        Assert::assertSame('openid email profile', $query['scope']);
        Assert::assertSame('signed-state', $query['state']);
        Assert::assertSame(self::NONCE, $query['nonce']);
    }

    #[Test]
    public function keeps_query_parameters_an_authorization_endpoint_already_carries(): void
    {
        // Entra ID publishes an authorization endpoint with its tenant in the query string, so
        // appending with a bare "?" would produce a URL with two of them.
        // Arrange
        $http = $this->http(discovery: $this->discovery(['authorization_endpoint' => self::ISSUER.'/auth?tenant=acme']));

        // Act
        $url = $this->client($http)->authorizationUrl('signed-state', self::NONCE);

        // Assert
        Assert::assertStringStartsWith(self::ISSUER.'/auth?tenant=acme&', $url);
        \parse_str((string) \parse_url($url, \PHP_URL_QUERY), $query);
        Assert::assertSame('acme', $query['tenant']);
        Assert::assertSame('signed-state', $query['state']);
    }

    #[Test]
    public function exchanges_the_code_and_returns_the_verified_identity(): void
    {
        // Arrange
        $sent = [];
        $http = $this->http(onTokenRequest: static function (array $options) use (&$sent): void {
            $sent = $options;
        });

        // Act
        $identity = $this->client($http)->exchangeCode('the-code', self::NONCE);

        // Assert
        Assert::assertSame('dex-subject-1', $identity->subject);
        Assert::assertSame('manager@example.test', $identity->email);
        Assert::assertSame('A Manager', $identity->name);

        $body = (string) ($sent['body'] ?? '');
        Assert::assertStringContainsString('grant_type=authorization_code', $body);
        Assert::assertStringContainsString('code=the-code', $body);
        Assert::assertStringContainsString('client_secret='.self::CLIENT_SECRET, $body);
        Assert::assertStringContainsString('redirect_uri=http%3A%2F%2Flocalhost%3A4200%2Fauth%2Foidc%2Fcallback', $body);
    }

    #[Test]
    public function takes_a_missing_name_as_no_name_rather_than_failing(): void
    {
        // Dex's SAML connector fills `name` from whichever attribute the operator mapped, and
        // an instance that mapped none still has a usable sign-in.
        // Arrange
        $claims = $this->claims();
        unset($claims['name']);

        // Act
        $identity = $this->client($this->http(claims: $claims))->exchangeCode('the-code', self::NONCE);

        // Assert
        Assert::assertSame('', $identity->name);
    }

    #[Test]
    public function refuses_a_token_minted_for_another_client(): void
    {
        $this->assertRefusesClaims(['aud' => 'some-other-client'], 'claims were rejected');
    }

    #[Test]
    public function refuses_a_token_from_another_issuer(): void
    {
        $this->assertRefusesClaims(['iss' => 'https://attacker.example.test'], 'claims were rejected');
    }

    #[Test]
    public function refuses_an_expired_token(): void
    {
        $this->assertRefusesClaims(['exp' => \strtotime(self::NOW) - 3600], 'claims were rejected');
    }

    #[Test]
    public function refuses_a_token_issued_for_a_different_flow(): void
    {
        // Without the nonce check, a token the issuer minted for an earlier sign-in — or one
        // captured from another flow entirely — would be as good as this flow's own.
        $this->assertRefusesClaims(['nonce' => 'a-nonce-from-another-flow'], 'claims were rejected');
    }

    #[Test]
    public function refuses_a_token_that_carries_no_nonce(): void
    {
        $claims = $this->claims();
        unset($claims['nonce']);

        $this->assertRefusesExactClaims($claims, 'claims were rejected');
    }

    #[Test]
    public function refuses_a_token_whose_email_the_issuer_has_not_verified(): void
    {
        // Taking an unverified address on trust is how one person signs in as another: claim
        // the victim's address at the issuer and the account match hands over their account.
        $this->assertRefusesClaims(['email_verified' => false], 'not marked this email address as verified');
    }

    #[Test]
    public function refuses_a_token_that_carries_no_email(): void
    {
        $claims = $this->claims();
        unset($claims['email']);

        $this->assertRefusesExactClaims($claims, 'carries no email address');
    }

    #[Test]
    public function refuses_a_token_signed_with_the_client_secret(): void
    {
        // Both sides hold the client secret, so an HMAC-signed token proves nothing about who
        // minted it. Accepting one would be an authentication bypass, not a validation slip.
        // Arrange
        $hmacKey = JWKFactory::createFromSecret(self::CLIENT_SECRET, ['alg' => 'HS256']);
        $forged = $this->serialize(
            new JWSBuilder(new AlgorithmManager([new HS256()]))
                ->create()
                ->withPayload(\json_encode($this->claims(), \JSON_THROW_ON_ERROR))
                ->addSignature($hmacKey, ['alg' => 'HS256'])
                ->build()
        );

        // Act
        $exception = $this->catchExchange($this->http(idToken: $forged));

        // Assert
        Assert::assertInstanceOf(OidcAuthenticationFailedException::class, $exception);
        Assert::assertStringContainsString('algorithm this instance does not accept', $exception->reason);
    }

    #[Test]
    public function refuses_a_token_signed_with_a_key_the_issuer_does_not_publish(): void
    {
        // Arrange
        $foreign = JWKFactory::createRSAKey(2048, ['alg' => 'RS256', 'kid' => 'attacker-key']);
        $forged = $this->idToken($this->claims(), $foreign);

        // Act
        $exception = $this->catchExchange($this->http(idToken: $forged));

        // Assert
        Assert::assertInstanceOf(OidcAuthenticationFailedException::class, $exception);
        Assert::assertStringContainsString('signature does not verify', $exception->reason);
    }

    #[Test]
    public function refetches_the_signing_keys_once_when_the_issuer_has_rotated_them(): void
    {
        // Dex rolls its signing key every few hours, so a token signed with a key the cached
        // set predates is ordinary rather than suspicious.
        // Arrange
        $jwksRequests = 0;
        $cache = new ArrayAdapter();
        $rotated = $this->rotatedKey();

        $http = new MockHttpClient(function (string $method, string $url) use (&$jwksRequests, $rotated): MockResponse {
            if (\str_contains($url, '/.well-known/openid-configuration')) {
                return $this->jsonResponse($this->discovery());
            }

            if (\str_contains($url, '/keys')) {
                ++$jwksRequests;

                // The first answer is the old key, which lands in the cache; the second has
                // the new one, which is what the token is actually signed with.
                return $this->jsonResponse((1 === $jwksRequests ? new JWKSet([$this->signingKey()->toPublic()]) : new JWKSet([$rotated->toPublic()]))->jsonSerialize());
            }

            return $this->jsonResponse(['id_token' => $this->idToken($this->claims(), $rotated)]);
        });

        // Act
        $identity = $this->client($http, cache: $cache)->exchangeCode('the-code', self::NONCE);

        // Assert
        Assert::assertSame('dex-subject-1', $identity->subject);
        Assert::assertSame(2, $jwksRequests);
    }

    #[Test]
    public function refuses_a_discovery_document_that_declares_a_different_issuer(): void
    {
        // Arrange
        $http = $this->http(discovery: $this->discovery(['issuer' => 'https://somewhere.else.test']));

        // Act
        $exception = $this->catchExchange($http);

        // Assert
        Assert::assertInstanceOf(OidcAuthenticationFailedException::class, $exception);
        Assert::assertStringContainsString('declares a different issuer', $exception->reason);
    }

    #[Test]
    public function accepts_a_discovery_document_whose_issuer_differs_only_by_a_trailing_slash(): void
    {
        // Arrange
        $http = $this->http(discovery: $this->discovery(['issuer' => self::ISSUER.'/']));

        // Act
        $identity = $this->client($http)->exchangeCode('the-code', self::NONCE);

        // Assert
        Assert::assertSame('dex-subject-1', $identity->subject);
    }

    #[Test]
    public function refuses_endpoints_that_are_not_https(): void
    {
        // Arrange
        $http = $this->http(discovery: $this->discovery(['token_endpoint' => 'http://sso.example.test/token']));

        // Act
        $exception = $this->catchExchange($http);

        // Assert
        Assert::assertInstanceOf(OidcAuthenticationFailedException::class, $exception);
        Assert::assertStringContainsString('no usable https token_endpoint', $exception->reason);
    }

    #[Test]
    public function refuses_a_discovery_document_that_cannot_be_read(): void
    {
        // Arrange
        $http = new MockHttpClient(static fn (): MockResponse => new MockResponse('nope', ['http_code' => 500]));

        // Act
        $exception = $this->catchExchange($http);

        // Assert
        Assert::assertInstanceOf(OidcAuthenticationFailedException::class, $exception);
        Assert::assertStringContainsString('discovery document could not be read', $exception->reason);
    }

    #[Test]
    public function refuses_when_the_issuer_rejects_the_code(): void
    {
        // Arrange
        $http = new MockHttpClient(function (string $method, string $url): MockResponse {
            if (\str_contains($url, '/.well-known/openid-configuration')) {
                return $this->jsonResponse($this->discovery());
            }

            return new MockResponse('{"error":"invalid_grant"}', ['http_code' => 400]);
        });

        // Act
        $exception = $this->catchExchange($http);

        // Assert
        Assert::assertInstanceOf(OidcAuthenticationFailedException::class, $exception);
        Assert::assertStringContainsString('rejected the authorization code', $exception->reason);
    }

    #[Test]
    public function refuses_a_token_response_without_an_id_token(): void
    {
        // Arrange
        $http = $this->http(tokenResponse: ['access_token' => 'at']);

        // Act
        $exception = $this->catchExchange($http);

        // Assert
        Assert::assertInstanceOf(OidcAuthenticationFailedException::class, $exception);
        Assert::assertStringContainsString('returned no id_token', $exception->reason);
    }

    #[Test]
    public function refuses_an_id_token_that_is_not_a_jws(): void
    {
        // Arrange
        $http = $this->http(idToken: 'this-is-not-a-token');

        // Act
        $exception = $this->catchExchange($http);

        // Assert
        Assert::assertInstanceOf(OidcAuthenticationFailedException::class, $exception);
        Assert::assertStringContainsString('not a well-formed JWS', $exception->reason);
    }

    #[Test]
    public function refuses_to_start_a_flow_when_no_issuer_is_configured(): void
    {
        // Act
        $exception = null;
        try {
            $this->client(new MockHttpClient(), issuer: '')->authorizationUrl('state', self::NONCE);
        } catch (\Throwable $throwable) {
            $exception = $throwable;
        }

        // Assert
        Assert::assertInstanceOf(OidcAuthenticationFailedException::class, $exception);
        Assert::assertStringContainsString('no OIDC issuer is configured', $exception->reason);
    }

    #[Test]
    public function reads_discovery_once_and_then_serves_it_from_the_cache(): void
    {
        // Arrange
        $discoveryRequests = 0;
        $cache = new ArrayAdapter();
        $http = new MockHttpClient(function (string $method, string $url) use (&$discoveryRequests): MockResponse {
            if (\str_contains($url, '/.well-known/openid-configuration')) {
                ++$discoveryRequests;
            }

            return $this->jsonResponse(\str_contains($url, '/.well-known/openid-configuration') ? $this->discovery() : []);
        });

        // Act
        $client = $this->client($http, cache: $cache);
        $client->authorizationUrl('state-one', self::NONCE);
        $client->authorizationUrl('state-two', self::NONCE);

        // Assert
        Assert::assertSame(1, $discoveryRequests);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function assertRefusesClaims(array $overrides, string $expectedReason): void
    {
        $this->assertRefusesExactClaims($overrides + $this->claims(), $expectedReason);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function assertRefusesExactClaims(array $claims, string $expectedReason): void
    {
        // Act
        $exception = $this->catchExchange($this->http(claims: $claims));

        // Assert
        Assert::assertInstanceOf(OidcAuthenticationFailedException::class, $exception);
        Assert::assertStringContainsString($expectedReason, $exception->reason);
    }

    private function catchExchange(MockHttpClient $http): ?\Throwable
    {
        try {
            $this->client($http)->exchangeCode('the-code', self::NONCE);
        } catch (\Throwable $throwable) {
            return $throwable;
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null      $discovery
     * @param array<string, mixed>|null      $claims
     * @param array<string, mixed>|null      $tokenResponse
     * @param (callable(array<mixed>): void) $onTokenRequest
     */
    private function http(
        ?array $discovery = null,
        ?array $claims = null,
        ?string $idToken = null,
        ?array $tokenResponse = null,
        ?callable $onTokenRequest = null,
    ): MockHttpClient {
        $discovery ??= $this->discovery();
        $token = $idToken ?? $this->idToken($claims ?? $this->claims());

        return new MockHttpClient(function (string $method, string $url, array $options) use ($discovery, $token, $tokenResponse, $onTokenRequest): MockResponse {
            if (\str_contains($url, '/.well-known/openid-configuration')) {
                return $this->jsonResponse($discovery);
            }

            if (\str_contains($url, '/keys')) {
                return $this->jsonResponse(new JWKSet([$this->signingKey()->toPublic()])->jsonSerialize());
            }

            if (null !== $onTokenRequest) {
                $onTokenRequest($options);
            }

            return $this->jsonResponse($tokenResponse ?? ['id_token' => $token]);
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(array $payload): MockResponse
    {
        return new MockResponse(\json_encode($payload, \JSON_THROW_ON_ERROR), ['http_code' => 200]);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function discovery(array $overrides = []): array
    {
        return $overrides + [
            'issuer' => self::ISSUER,
            'authorization_endpoint' => self::ISSUER.'/auth',
            'token_endpoint' => self::ISSUER.'/token',
            'jwks_uri' => self::ISSUER.'/keys',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function claims(): array
    {
        $now = \strtotime(self::NOW);

        return [
            'iss' => self::ISSUER,
            'sub' => 'dex-subject-1',
            'aud' => self::CLIENT_ID,
            'iat' => $now,
            'exp' => $now + 3600,
            'nonce' => self::NONCE,
            'email' => 'manager@example.test',
            'email_verified' => true,
            'name' => 'A Manager',
        ];
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function idToken(array $claims, ?JWK $key = null): string
    {
        $key ??= $this->signingKey();

        return $this->serialize(
            new JWSBuilder(new AlgorithmManager([new RS256()]))
                ->create()
                ->withPayload(\json_encode($claims, \JSON_THROW_ON_ERROR))
                ->addSignature($key, ['alg' => 'RS256', 'kid' => $key->get('kid')])
                ->build()
        );
    }

    private function serialize(JWS $jws): string
    {
        return new CompactSerializer()->serialize($jws, 0);
    }

    private function client(
        MockHttpClient $http,
        string $issuer = self::ISSUER,
        string $clientId = self::CLIENT_ID,
        string $clientSecret = self::CLIENT_SECRET,
        ?ArrayAdapter $cache = null,
    ): OidcClient {
        $cache ??= new ArrayAdapter();
        $reader = new OidcHttpReader($http, $this->createStub(LoggerInterface::class));
        $clock = new MockClock(new \DateTimeImmutable(self::NOW));

        return new OidcClient(
            $reader,
            new OidcIdTokenVerifier(new OidcJwksProvider($reader, $cache), new OidcSignatureAlgorithms(), $clock),
            $cache,
            $issuer,
            $clientId,
            $clientSecret,
            'openid email profile',
            'http://localhost:4200',
        );
    }

    private function signingKey(): JWK
    {
        return self::$signingKey ??= JWKFactory::createRSAKey(2048, ['alg' => 'RS256', 'use' => 'sig', 'kid' => 'key-one']);
    }

    private function rotatedKey(): JWK
    {
        return self::$rotatedKey ??= JWKFactory::createRSAKey(2048, ['alg' => 'RS256', 'use' => 'sig', 'kid' => 'key-two']);
    }
}
