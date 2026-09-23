<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Http\Auth\GoogleOAuth;

use App\Identity\Account\Application\Command\GoogleOAuthLogin\GoogleOAuthLoginCommand;
use App\Identity\Account\Infrastructure\Api\Auth\GoogleOAuth\GoogleOAuthCallbackController;
use App\Identity\Account\Infrastructure\Service\GoogleOAuthClient;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[CoversClass(GoogleOAuthCallbackController::class)]
final class GoogleOAuthCallbackControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function returns_bad_request_when_payload_is_invalid_json(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->request(
            'POST',
            '/api/identity/auth/google/callback',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"idToken":"broken"'
        );

        // Assert
        Assert::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('Invalid request body', $response['error'] ?? null);
    }

    #[Test]
    public function returns_bad_request_when_id_token_is_missing(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->jsonRequest('POST', '/api/identity/auth/google/callback', [
            'marketingConsent' => true,
        ]);

        // Assert
        Assert::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('Missing idToken', $response['error'] ?? null);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function returns_unauthorized_when_google_oauth_verification_fails(): void
    {
        // Arrange
        $client = self::createClient();
        $client->disableReboot();
        $this->replaceGoogleClientWithStatusAndPayload(401, []);

        // Act
        $client->jsonRequest('POST', '/api/identity/auth/google/callback', [
            'idToken' => 'invalid-token',
        ]);

        // Assert
        Assert::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('Authentication failed', $response['error'] ?? null);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function returns_token_and_dispatches_command_with_marketing_consent(): void
    {
        // Arrange
        $client = self::createClient();
        $client->disableReboot();
        $this->replaceGoogleClientWithStatusAndPayload(200, [
            'aud' => 'google-client-id',
            'email_verified' => true,
            'email' => 'employee@example.com',
            'sub' => 'google-user-123',
            'name' => 'Employee',
            'picture' => 'https://example.com/picture.jpg',
        ]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message): Envelope {
                Assert::assertInstanceOf(GoogleOAuthLoginCommand::class, $message);
                Assert::assertSame('employee@example.com', $message->email);
                Assert::assertSame('google-user-123', $message->googleId);
                Assert::assertTrue($message->marketingConsent);

                return new Envelope($message, [new HandledStamp(new \App\Identity\Account\Application\Command\TokensDto('jwt-token-value', null), 'test_handler')]);
            });
        self::getContainer()->set(MessageBusInterface::class, $bus);

        // Act
        $client->jsonRequest('POST', '/api/identity/auth/google/callback', [
            'idToken' => 'valid-token',
            'marketingConsent' => true,
        ]);

        // Assert
        Assert::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('jwt-token-value', $response['token']['jwtToken'] ?? null);
        Assert::assertArrayNotHasKey('refreshToken', $response['token'] ?? []);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function returns_internal_server_error_when_dispatch_fails_unexpectedly(): void
    {
        // Arrange
        $client = self::createClient();
        $client->disableReboot();
        $this->replaceGoogleClientWithStatusAndPayload(200, [
            'aud' => 'google-client-id',
            'email_verified' => true,
            'email' => 'employee@example.com',
            'sub' => 'google-user-123',
        ]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus
            ->method('dispatch')
            ->willThrowException(new \LogicException('Unexpected dispatch failure'));
        self::getContainer()->set(MessageBusInterface::class, $bus);

        // Act
        $client->jsonRequest('POST', '/api/identity/auth/google/callback', [
            'idToken' => 'valid-token',
        ]);

        // Assert
        Assert::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $client->getResponse()->getStatusCode());
        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        Assert::assertSame('Internal server error', $response['error'] ?? null);
    }

    private function replaceGoogleClientWithStatusAndPayload(int $statusCode, array $payload): void
    {
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse
            ->method('getStatusCode')
            ->willReturn($statusCode);
        $httpResponse
            ->method('toArray')
            ->willReturn($payload);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->method('request')
            ->with('GET', 'https://oauth2.googleapis.com/tokeninfo', $this->callback(static function (mixed $options): bool {
                if (!\is_array($options) || !isset($options['query']) || !\is_array($options['query'])) {
                    return false;
                }

                return isset($options['query']['id_token']) && \is_string($options['query']['id_token']);
            }))
            ->willReturn($httpResponse);

        $googleClient = new GoogleOAuthClient($httpClient, 'google-client-id', 'google-client-secret');
        self::getContainer()->set(GoogleOAuthClient::class, $googleClient);
    }
}
