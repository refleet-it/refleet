<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Http\Auth\GoogleOAuth;

use App\Identity\Account\Infrastructure\Api\Auth\GoogleOAuth\GoogleOAuthUrlController;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(GoogleOAuthUrlController::class)]
final class GoogleOAuthUrlControllerTest extends WebTestCase
{
    #[Test]
    public function returns_authorization_url_and_state_with_expected_contract(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->request('GET', '/api/identity/auth/google/url');

        // Assert
        Assert::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        Assert::assertIsArray($payload);
        Assert::assertArrayHasKey('url', $payload);
        Assert::assertArrayHasKey('state', $payload);
        Assert::assertIsString($payload['url']);
        Assert::assertIsString($payload['state']);
        Assert::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $payload['state']);

        $queryString = (string) \parse_url($payload['url'], \PHP_URL_QUERY);
        \parse_str($queryString, $query);

        Assert::assertSame($payload['state'], $query['state'] ?? null);
        Assert::assertSame('code', $query['response_type'] ?? null);
        Assert::assertSame('openid email profile', $query['scope'] ?? null);
        Assert::assertSame('offline', $query['access_type'] ?? null);
        Assert::assertSame('consent', $query['prompt'] ?? null);
    }

    #[Test]
    public function uses_default_redirect_uri_when_redirect_uri_query_param_is_missing(): void
    {
        // Arrange
        $client = self::createClient();
        $previous = $_ENV['GOOGLE_OAUTH_REDIRECT_URI'] ?? null;
        $_ENV['GOOGLE_OAUTH_REDIRECT_URI'] = 'https://frontend.example.test/auth/default';

        // Act
        $client->request('GET', '/api/identity/auth/google/url');

        // Assert
        $payload = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $queryString = (string) \parse_url((string) $payload['url'], \PHP_URL_QUERY);
        \parse_str($queryString, $query);

        Assert::assertSame('https://frontend.example.test/auth/default', $query['redirect_uri'] ?? null);

        if (null === $previous) {
            unset($_ENV['GOOGLE_OAUTH_REDIRECT_URI']);

            return;
        }

        $_ENV['GOOGLE_OAUTH_REDIRECT_URI'] = $previous;
    }

    #[Test]
    public function uses_redirect_uri_query_param_over_default_redirect_uri(): void
    {
        // Arrange
        $client = self::createClient();
        $previous = $_ENV['GOOGLE_OAUTH_REDIRECT_URI'] ?? null;
        $_ENV['GOOGLE_OAUTH_REDIRECT_URI'] = 'https://frontend.example.test/auth/default';
        $providedRedirectUri = 'https://frontend.example.test/auth/override';

        // Act
        $client->request('GET', '/api/identity/auth/google/url?redirect_uri='.\urlencode($providedRedirectUri));

        // Assert
        $payload = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $queryString = (string) \parse_url((string) $payload['url'], \PHP_URL_QUERY);
        \parse_str($queryString, $query);

        Assert::assertSame($providedRedirectUri, $query['redirect_uri'] ?? null);

        if (null === $previous) {
            unset($_ENV['GOOGLE_OAUTH_REDIRECT_URI']);

            return;
        }

        $_ENV['GOOGLE_OAUTH_REDIRECT_URI'] = $previous;
    }

    #[Test]
    public function generates_unique_state_between_consecutive_requests(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->request('GET', '/api/identity/auth/google/url');

        $firstPayload = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('GET', '/api/identity/auth/google/url');
        $secondPayload = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        // Assert
        Assert::assertNotSame($firstPayload['state'] ?? null, $secondPayload['state'] ?? null);
    }
}
