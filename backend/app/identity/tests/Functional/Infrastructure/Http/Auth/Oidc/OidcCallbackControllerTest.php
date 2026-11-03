<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Http\Auth\Oidc;

use App\Identity\Account\Domain\Account\Exception\OidcAuthenticationFailedException;
use App\Identity\Account\Domain\Account\Service\OidcClientInterface;
use App\Identity\Account\Domain\Account\ValueObject\OidcIdentity;
use App\Identity\Account\Infrastructure\Api\Auth\Oidc\OidcCallbackController;
use App\Identity\Account\Infrastructure\Factory\AccessTokenCookieFactory;
use App\Identity\Account\Infrastructure\Factory\OidcFlowCookieFactory;
use App\Identity\Account\Infrastructure\Factory\RefreshTokenCookieFactory;
use App\Identity\Account\Infrastructure\Security\OidcStateSigner;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[CoversClass(OidcCallbackController::class)]
final class OidcCallbackControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function signs_in_and_issues_this_instance_session_on_a_verified_token(): void
    {
        // Arrange
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(OidcClientInterface::class, $this->oidcClient(
            identity: new OidcIdentity('dex-subject-1', 'sso.newcomer@example.test', 'A Newcomer'),
        ));
        $state = $this->startFlow($client);

        // Act
        $client->jsonRequest('POST', '/api/identity/auth/oidc/callback', [
            'code' => 'the-code',
            'state' => $state,
        ]);

        // Assert
        Assert::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertIsArray($payload['token'] ?? null);
        Assert::assertNotSame('', $payload['token']['jwtToken'] ?? '');

        $cookies = [];
        foreach ($client->getResponse()->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie;
        }

        Assert::assertArrayHasKey(AccessTokenCookieFactory::COOKIE_NAME, $cookies);
        Assert::assertArrayHasKey(RefreshTokenCookieFactory::COOKIE_NAME, $cookies);
        // One code buys one sign-in, so the flow cookie is spent either way.
        Assert::assertArrayHasKey(OidcFlowCookieFactory::COOKIE_NAME, $cookies);
        Assert::assertSame('', $cookies[OidcFlowCookieFactory::COOKIE_NAME]->getValue());
    }

    #[Test]
    public function refuses_a_flow_that_was_not_started_in_this_browser(): void
    {
        // The login-CSRF case: the state is one this instance really issued, but to somebody
        // else's browser, so the cookie that should accompany it is missing.
        // Arrange
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(OidcClientInterface::class, $this->oidcClient());
        $signer = self::getContainer()->get(OidcStateSigner::class);
        Assert::assertInstanceOf(OidcStateSigner::class, $signer);

        // Act
        $client->jsonRequest('POST', '/api/identity/auth/oidc/callback', [
            'code' => 'the-code',
            'state' => $signer->sign($signer->newNonce()),
        ]);

        // Assert
        Assert::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $payload = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('INVALID_OIDC_STATE', $payload['error'] ?? null);
    }

    #[Test]
    public function refuses_a_state_that_does_not_match_the_cookie(): void
    {
        // Arrange
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(OidcClientInterface::class, $this->oidcClient());
        $this->startFlow($client);
        $signer = self::getContainer()->get(OidcStateSigner::class);
        Assert::assertInstanceOf(OidcStateSigner::class, $signer);

        // Act
        $client->jsonRequest('POST', '/api/identity/auth/oidc/callback', [
            'code' => 'the-code',
            'state' => $signer->sign($signer->newNonce()),
        ]);

        // Assert
        Assert::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function answers_unauthorized_when_the_provider_will_not_vouch_for_the_code(): void
    {
        // Arrange
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(OidcClientInterface::class, $this->oidcClient(
            failure: new OidcAuthenticationFailedException('the issuer rejected the authorization code'),
        ));
        $state = $this->startFlow($client);

        // Act
        $client->jsonRequest('POST', '/api/identity/auth/oidc/callback', [
            'code' => 'the-code',
            'state' => $state,
        ]);

        // Assert
        Assert::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        $payload = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('OIDC_AUTHENTICATION_FAILED', $payload['error'] ?? null);
        // The reason stays in the log: told apart, the failures let a caller probe the flow.
        Assert::assertStringNotContainsString('authorization code', (string) $client->getResponse()->getContent());
    }

    #[Test]
    public function answers_conflict_on_an_instance_with_no_provider_configured(): void
    {
        // Arrange
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(OidcClientInterface::class, $this->oidcClient(configured: false));

        // Act
        $client->jsonRequest('POST', '/api/identity/auth/oidc/callback', [
            'code' => 'the-code',
            'state' => 'anything',
        ]);

        // Assert
        Assert::assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());
    }

    #[Test]
    public function rejects_a_payload_with_no_code(): void
    {
        // Arrange
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(OidcClientInterface::class, $this->oidcClient());

        // Act
        $client->jsonRequest('POST', '/api/identity/auth/oidc/callback', ['state' => 'anything']);

        // Assert
        Assert::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    /**
     * Starts a flow the way a browser would, so the callback sees the cookie the url endpoint
     * set, and returns the state that went with it.
     */
    private function startFlow(KernelBrowser $client): string
    {
        $client->request('GET', '/api/identity/auth/oidc/url');

        $payload = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertIsArray($payload);
        \parse_str((string) \parse_url((string) $payload['url'], \PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;
        Assert::assertIsString($state);

        // BrowserKit does not scope its cookie jar the way a browser does, and the flow cookie
        // is set on /api/identity/auth/oidc, so put it back on a path the next request sends.
        $client->getCookieJar()->set(new Cookie(OidcFlowCookieFactory::COOKIE_NAME, $state, path: '/'));

        return $state;
    }

    private function oidcClient(
        bool $configured = true,
        ?OidcIdentity $identity = null,
        ?\Throwable $failure = null,
    ): OidcClientInterface {
        return new class($configured, $identity ?? new OidcIdentity('dex-subject-0', 'sso.user@example.test', 'SSO User'), $failure) implements OidcClientInterface {
            public function __construct(
                private readonly bool $configured,
                private readonly OidcIdentity $identity,
                private readonly ?\Throwable $failure,
            ) {
            }

            #[\Override]
            public function isConfigured(): bool
            {
                return $this->configured;
            }

            #[\Override]
            public function authorizationUrl(string $state, string $nonce): string
            {
                return 'https://sso.example.test/auth?'.\http_build_query(['state' => $state, 'nonce' => $nonce]);
            }

            #[\Override]
            public function exchangeCode(string $code, string $expectedNonce): OidcIdentity
            {
                if (null !== $this->failure) {
                    throw $this->failure;
                }

                return $this->identity;
            }
        };
    }
}
