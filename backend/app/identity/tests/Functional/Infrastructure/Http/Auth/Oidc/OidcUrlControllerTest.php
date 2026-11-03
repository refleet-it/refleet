<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Http\Auth\Oidc;

use App\Identity\Account\Domain\Account\Service\OidcClientInterface;
use App\Identity\Account\Domain\Account\ValueObject\OidcIdentity;
use App\Identity\Account\Infrastructure\Api\Auth\Oidc\OidcUrlController;
use App\Identity\Account\Infrastructure\Factory\OidcFlowCookieFactory;
use App\Identity\Account\Infrastructure\Security\OidcStateSigner;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(OidcUrlController::class)]
final class OidcUrlControllerTest extends WebTestCase
{
    #[Test]
    public function returns_the_authorization_url_and_pins_the_flow_to_this_browser(): void
    {
        // Arrange
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(OidcClientInterface::class, $this->oidcClient());

        // Act
        $client->request('GET', '/api/identity/auth/oidc/url');

        // Assert
        Assert::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertIsArray($payload);
        Assert::assertIsString($payload['url'] ?? null);

        \parse_str((string) \parse_url((string) $payload['url'], \PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;
        Assert::assertIsString($state);
        Assert::assertIsString($query['nonce'] ?? null);

        // The state is signed, so it verifies, and what it carries is the nonce that went out
        // in the same request — the pair is what makes a replayed token useless.
        $signer = self::getContainer()->get(OidcStateSigner::class);
        Assert::assertInstanceOf(OidcStateSigner::class, $signer);
        Assert::assertSame($query['nonce'], $signer->verify($state));

        $cookie = $client->getResponse()->headers->getCookies()[0] ?? null;
        Assert::assertNotNull($cookie);
        Assert::assertSame(OidcFlowCookieFactory::COOKIE_NAME, $cookie->getName());
        Assert::assertSame($state, $cookie->getValue());
        Assert::assertTrue($cookie->isHttpOnly());
    }

    #[Test]
    public function answers_conflict_on_an_instance_with_no_provider_configured(): void
    {
        // Arrange
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(OidcClientInterface::class, $this->oidcClient(configured: false));

        // Act
        $client->request('GET', '/api/identity/auth/oidc/url');

        // Assert
        Assert::assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());
        $payload = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('OIDC_NOT_CONFIGURED', $payload['error'] ?? null);
    }

    private function oidcClient(bool $configured = true): OidcClientInterface
    {
        return new class($configured) implements OidcClientInterface {
            public function __construct(private readonly bool $configured)
            {
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
                throw new \LogicException('not used by this test');
            }
        };
    }
}
