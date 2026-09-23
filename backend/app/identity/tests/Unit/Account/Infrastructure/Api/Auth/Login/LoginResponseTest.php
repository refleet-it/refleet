<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Api\Auth\Login;

use App\Identity\Account\Infrastructure\Api\Auth\Login\LoginResponse;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoginResponse::class)]
final class LoginResponseTest extends TestCase
{
    #[Test]
    public function exposes_token_and_refresh_token_when_both_are_provided(): void
    {
        // Arrange
        $token = 'jwt-token';
        $refreshToken = 'refresh-token';

        // Act
        $response = new LoginResponse($token, $refreshToken);

        // Assert
        Assert::assertSame($token, $response->token);
        Assert::assertSame($refreshToken, $response->refreshToken);
    }

    #[Test]
    public function allows_null_refresh_token(): void
    {
        // Arrange
        $token = 'jwt-token';

        // Act
        $response = new LoginResponse($token, null);

        // Assert
        Assert::assertSame($token, $response->token);
        Assert::assertNull($response->refreshToken);
    }

    #[Test]
    public function throws_when_trying_to_mutate_readonly_property(): void
    {
        // Arrange
        $response = new LoginResponse('jwt-token', 'refresh-token');

        // Act
        $exception = null;

        try {
            $response->token = 'new-jwt-token';
        } catch (\Error $error) {
            $exception = $error;
        }

        // Assert
        Assert::assertInstanceOf(\Error::class, $exception);
        Assert::assertSame('jwt-token', $response->token);
    }
}
