<?php

declare(strict_types=1);

namespace App\Tests\Unit\Organization\GitLabConnection\Infrastructure\Service;

use App\Organization\GitLabConnection\Domain\Connection\Exception\InvalidGitLabCredentialsException;
use App\Organization\GitLabConnection\Infrastructure\Service\GitLabOAuthClient;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(GitLabOAuthClient::class)]
final class GitLabOAuthClientTest extends TestCase
{
    #[Test]
    public function is_configured_only_with_both_client_id_and_secret(): void
    {
        Assert::assertTrue($this->client(new MockHttpClient())->isConfigured());
        Assert::assertFalse($this->client(new MockHttpClient(), clientId: '')->isConfigured());
        Assert::assertFalse($this->client(new MockHttpClient(), clientSecret: '')->isConfigured());
    }

    #[Test]
    public function builds_the_authorization_url_with_the_frontend_callback(): void
    {
        // Act
        $url = $this->client(new MockHttpClient())->authorizationUrl('signed-state');

        // Assert
        Assert::assertStringStartsWith('https://gitlab.com/oauth/authorize?', $url);
        \parse_str((string) \parse_url($url, \PHP_URL_QUERY), $query);
        Assert::assertSame('client-id', $query['client_id']);
        Assert::assertSame('https://app.refleet.it/dashboard/settings/gitlab/callback', $query['redirect_uri']);
        Assert::assertSame('code', $query['response_type']);
        Assert::assertSame('api', $query['scope']);
        Assert::assertSame('signed-state', $query['state']);
    }

    #[Test]
    public function exchanges_the_code_for_a_token_pair(): void
    {
        // Arrange
        $sent = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = ['method' => $method, 'url' => $url, 'body' => $options['body']];

            return new MockResponse(\json_encode(['access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 7200], \JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        // Act
        $tokens = $this->client($http)->exchangeCode('the-code');

        // Assert
        Assert::assertSame('POST', $sent['method']);
        Assert::assertSame('https://gitlab.com/oauth/token', $sent['url']);
        Assert::assertStringContainsString('grant_type=authorization_code', (string) $sent['body']);
        Assert::assertStringContainsString('code=the-code', (string) $sent['body']);
        Assert::assertStringContainsString('client_secret=client-secret', (string) $sent['body']);
        Assert::assertSame('at', $tokens->accessToken);
        Assert::assertSame('rt', $tokens->refreshToken);
        Assert::assertEqualsWithDelta(\time() + 7200, $tokens->expiresAt->getTimestamp(), 5);
    }

    #[Test]
    public function refreshes_with_the_refresh_token_grant(): void
    {
        // Arrange
        $body = '';
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): MockResponse {
            $body = $options['body'];

            return new MockResponse(\json_encode(['access_token' => 'at2', 'refresh_token' => 'rt2', 'expires_in' => 7200], \JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        // Act
        $tokens = $this->client($http)->refresh('rt1');

        // Assert
        Assert::assertStringContainsString('grant_type=refresh_token', (string) $body);
        Assert::assertStringContainsString('refresh_token=rt1', (string) $body);
        Assert::assertSame('rt2', $tokens->refreshToken);
    }

    #[Test]
    public function a_rejected_code_is_reported_as_invalid_credentials(): void
    {
        // Arrange
        $http = new MockHttpClient(new MockResponse('{"error":"invalid_grant"}', ['http_code' => 400]));

        // Assert
        $this->expectException(InvalidGitLabCredentialsException::class);

        // Act
        $this->client($http)->exchangeCode('bad');
    }

    #[Test]
    public function an_incomplete_token_response_is_reported_as_invalid_credentials(): void
    {
        // Arrange
        $http = new MockHttpClient(new MockResponse('{"access_token":"at"}', ['http_code' => 200]));

        // Assert
        $this->expectException(InvalidGitLabCredentialsException::class);

        // Act
        $this->client($http)->exchangeCode('code');
    }

    private function client(MockHttpClient $http, string $clientId = 'client-id', string $clientSecret = 'client-secret'): GitLabOAuthClient
    {
        return new GitLabOAuthClient($http, $clientId, $clientSecret, 'https://app.refleet.it/', 'https://gitlab.com');
    }
}
