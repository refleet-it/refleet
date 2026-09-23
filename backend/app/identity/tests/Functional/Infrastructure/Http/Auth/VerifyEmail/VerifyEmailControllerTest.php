<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity\Infrastructure\Http\Auth\VerifyEmail;

use App\Fixtures\Factory\Identity\EmailVerificationTokenFactory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class VerifyEmailControllerTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function verifies_email_and_returns_authentication_tokens_for_valid_token(): void
    {
        // Arrange
        $client = self::createClient();
        $verificationToken = 'verify-'.\bin2hex(\random_bytes(12));

        EmailVerificationTokenFactory::createOne([
            'token' => $verificationToken,
            'expiresAt' => new \DateTimeImmutable('+2 hours'),
        ]);

        // Act
        $client->jsonRequest('POST', '/api/identity/verify-email/'.$verificationToken);

        // Assert
        Assert::assertSame(200, $client->getResponse()->getStatusCode());
        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        Assert::assertIsArray($response);
        Assert::assertArrayHasKey('token', $response);
        Assert::assertIsArray($response['token'] ?? null);
        Assert::assertIsString($response['token']['jwtToken'] ?? null);
        Assert::assertNotSame('', $response['token']['jwtToken'] ?? null);
        Assert::assertArrayNotHasKey('refreshToken', $response['token'] ?? []);
    }

    #[Test]
    public function returns_not_found_when_verification_token_does_not_exist(): void
    {
        // Arrange
        $client = self::createClient();

        // Act
        $client->jsonRequest('POST', '/api/identity/verify-email/not-existing-token');

        // Assert
        Assert::assertSame(404, $client->getResponse()->getStatusCode());
        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        Assert::assertSame('EMAIL_VERIFICATION_TOKEN_NOT_FOUND', $response['error'] ?? null);
        Assert::assertSame('Email verification token not found.', $response['message'] ?? null);
    }

    #[Test]
    public function returns_bad_request_when_verification_token_is_expired(): void
    {
        // Arrange
        $client = self::createClient();
        $verificationToken = 'expired-'.\bin2hex(\random_bytes(12));

        EmailVerificationTokenFactory::createOne([
            'token' => $verificationToken,
            'expiresAt' => new \DateTimeImmutable('-1 minute'),
        ]);

        // Act
        $client->jsonRequest('POST', '/api/identity/verify-email/'.$verificationToken);

        // Assert
        Assert::assertSame(400, $client->getResponse()->getStatusCode());
        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        Assert::assertSame('EMAIL_VERIFICATION_TOKEN_EXPIRED', $response['error'] ?? null);
        Assert::assertSame('Email verification token has expired.', $response['message'] ?? null);
    }

    #[Test]
    public function cannot_use_same_verification_token_twice(): void
    {
        // Arrange
        $client = self::createClient();
        $verificationToken = 'single-use-'.\bin2hex(\random_bytes(12));

        EmailVerificationTokenFactory::createOne([
            'token' => $verificationToken,
            'expiresAt' => new \DateTimeImmutable('+2 hours'),
        ]);

        // Act
        $client->jsonRequest('POST', '/api/identity/verify-email/'.$verificationToken);
        $firstStatusCode = $client->getResponse()->getStatusCode();
        $client->jsonRequest('POST', '/api/identity/verify-email/'.$verificationToken);

        // Assert
        Assert::assertSame(200, $firstStatusCode);
        Assert::assertSame(400, $client->getResponse()->getStatusCode());
        $response = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        Assert::assertSame('EMAIL_VERIFICATION_TOKEN_ALREADY_USED', $response['error'] ?? null);
        Assert::assertSame('Email verification token has already been used.', $response['message'] ?? null);
    }
}
