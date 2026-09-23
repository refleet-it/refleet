<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Service;

use App\Identity\Account\Infrastructure\Service\GoogleOAuthClient;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(GoogleOAuthClient::class)]
final class GoogleOAuthClientTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;

    private GoogleOAuthClient $client;

    #[Test]
    public function verify_id_token_returns_normalized_user_payload_when_valid(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response
            ->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'aud' => 'google-client-id',
                'email_verified' => true,
                'email' => 'user@example.com',
                'sub' => 'google-sub-123',
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://oauth2.googleapis.com/tokeninfo',
                $this->callback(static function (array $options): bool {
                    Assert::assertSame(['id_token' => 'id-token'], $options['query'] ?? null);

                    return true;
                }),
            )
            ->willReturn($response);

        // Act
        $result = $this->client->verifyIdToken('id-token');

        // Assert
        Assert::assertSame('user@example.com', $result['email']);
        Assert::assertTrue($result['email_verified']);
        Assert::assertSame('google-sub-123', $result['sub']);
        Assert::assertSame('', $result['name']);
        Assert::assertSame('', $result['picture']);
    }

    #[Test]
    public function verify_id_token_throws_wrapped_exception_when_audience_does_not_match(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response
            ->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'aud' => 'different-client-id',
                'email_verified' => true,
                'email' => 'user@example.com',
                'sub' => 'google-sub-123',
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        try {
            // Act
            $this->client->verifyIdToken('id-token');
            Assert::fail('RuntimeException expected.');
        } catch (\RuntimeException $runtimeException) {
            // Assert
            Assert::assertStringContainsString('Failed to verify Google ID token: ID token not intended for this application', $runtimeException->getMessage());
            Assert::assertInstanceOf(\RuntimeException::class, $runtimeException->getPrevious());
            Assert::assertSame('ID token not intended for this application', $runtimeException->getPrevious()?->getMessage());
        }
    }

    #[Test]
    public function verify_id_token_requires_email_verified_boolean_true(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response
            ->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'aud' => 'google-client-id',
                'email_verified' => 'true',
                'email' => 'user@example.com',
                'sub' => 'google-sub-123',
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        try {
            // Act
            $this->client->verifyIdToken('id-token');
            Assert::fail('RuntimeException expected.');
        } catch (\RuntimeException $runtimeException) {
            // Assert
            Assert::assertStringContainsString('Failed to verify Google ID token: Email not verified by Google', $runtimeException->getMessage());
            Assert::assertInstanceOf(\RuntimeException::class, $runtimeException->getPrevious());
            Assert::assertSame('Email not verified by Google', $runtimeException->getPrevious()?->getMessage());
        }
    }

    #[Test]
    public function verify_id_token_throws_wrapped_exception_when_missing_required_fields(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response
            ->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'aud' => 'google-client-id',
                'email_verified' => true,
                'email' => 'user@example.com',
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        try {
            // Act
            $this->client->verifyIdToken('id-token');
            Assert::fail('RuntimeException expected.');
        } catch (\RuntimeException $runtimeException) {
            // Assert
            Assert::assertStringContainsString('Failed to verify Google ID token: Missing required fields in token data', $runtimeException->getMessage());
            Assert::assertInstanceOf(\RuntimeException::class, $runtimeException->getPrevious());
            Assert::assertSame('Missing required fields in token data', $runtimeException->getPrevious()?->getMessage());
        }
    }

    #[Test]
    public function exchange_code_for_token_returns_payload_and_casts_expires_in_to_int(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response
            ->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'access_token' => 'access-token',
                'id_token' => 'id-token',
                'expires_in' => '3600',
                'refresh_token' => 'refresh-token',
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://oauth2.googleapis.com/token',
                $this->callback(static function (array $options): bool {
                    Assert::assertSame([
                        'code' => 'auth-code',
                        'client_id' => 'google-client-id',
                        'client_secret' => 'google-client-secret',
                        'redirect_uri' => 'https://app.example.com/callback',
                        'grant_type' => 'authorization_code',
                    ], $options['body'] ?? null);

                    return true;
                }),
            )
            ->willReturn($response);

        // Act
        $result = $this->client->exchangeCodeForToken('auth-code', 'https://app.example.com/callback');

        // Assert
        Assert::assertSame('access-token', $result['access_token']);
        Assert::assertSame('id-token', $result['id_token']);
        Assert::assertSame(3600, $result['expires_in']);
        Assert::assertSame('refresh-token', $result['refresh_token']);
    }

    #[Test]
    public function exchange_code_for_token_returns_null_refresh_token_when_absent(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response
            ->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'access_token' => 'access-token',
                'id_token' => 'id-token',
                'expires_in' => 3600,
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        // Act
        $result = $this->client->exchangeCodeForToken('auth-code', 'https://app.example.com/callback');

        // Assert
        Assert::assertArrayHasKey('refresh_token', $result);
        Assert::assertNull($result['refresh_token']);
    }

    #[Test]
    public function exchange_code_for_token_throws_wrapped_exception_when_status_is_not_ok(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(401);
        $response
            ->expects($this->never())
            ->method('toArray');

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        try {
            // Act
            $this->client->exchangeCodeForToken('auth-code', 'https://app.example.com/callback');
            Assert::fail('RuntimeException expected.');
        } catch (\RuntimeException $runtimeException) {
            // Assert
            Assert::assertStringContainsString('Failed to exchange code for token: Failed to exchange authorization code', $runtimeException->getMessage());
            Assert::assertInstanceOf(\RuntimeException::class, $runtimeException->getPrevious());
            Assert::assertSame('Failed to exchange authorization code', $runtimeException->getPrevious()?->getMessage());
        }
    }

    #[Test]
    public function exchange_code_for_token_throws_wrapped_exception_when_required_fields_missing(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response
            ->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'access_token' => 'access-token',
                'expires_in' => 3600,
            ]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        try {
            // Act
            $this->client->exchangeCodeForToken('auth-code', 'https://app.example.com/callback');
            Assert::fail('RuntimeException expected.');
        } catch (\RuntimeException $runtimeException) {
            // Assert
            Assert::assertStringContainsString('Failed to exchange code for token: Missing required fields in token response', $runtimeException->getMessage());
            Assert::assertInstanceOf(\RuntimeException::class, $runtimeException->getPrevious());
            Assert::assertSame('Missing required fields in token response', $runtimeException->getPrevious()?->getMessage());
        }
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function get_authorization_url_contains_required_query_parameters(): void
    {
        // Arrange
        $redirectUri = 'https://app.example.com/oauth/callback?from=mobile';
        $state = 'state-value-123';

        // Act
        $url = $this->client->getAuthorizationUrl($redirectUri, $state);

        // Assert
        Assert::assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);

        $query = \parse_url($url, \PHP_URL_QUERY);
        Assert::assertIsString($query);

        $params = [];
        \parse_str($query, $params);

        Assert::assertSame('google-client-id', $params['client_id'] ?? null);
        Assert::assertSame($redirectUri, $params['redirect_uri'] ?? null);
        Assert::assertSame('code', $params['response_type'] ?? null);
        Assert::assertSame('openid email profile', $params['scope'] ?? null);
        Assert::assertSame($state, $params['state'] ?? null);
        Assert::assertSame('offline', $params['access_type'] ?? null);
        Assert::assertSame('consent', $params['prompt'] ?? null);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->client = new GoogleOAuthClient($this->httpClient, 'google-client-id', 'google-client-secret');
    }
}
