<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\VerifyEmail;

use App\Identity\Account\Application\Command\VerifyEmail\VerifyEmailCommand;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VerifyEmailCommand::class)]
final class VerifyEmailCommandTest extends TestCase
{
    #[Test]
    public function creates_command_with_token_preserving_exact_value(): void
    {
        // Arrange
        $token = '  verify-token.with-special_chars-123  ';

        // Act
        $command = new VerifyEmailCommand(token: $token);

        // Assert
        Assert::assertSame($token, $command->token);
    }

    #[Test]
    public function prevents_modifying_token_after_construction(): void
    {
        // Arrange
        $command = new VerifyEmailCommand(token: 'initial-token');

        // Act
        $exception = null;

        try {
            $command->token = 'changed-token';
        } catch (\Error $error) {
            $exception = $error;
        }

        // Assert
        Assert::assertInstanceOf(\Error::class, $exception);
        Assert::assertSame('Cannot modify readonly property '.VerifyEmailCommand::class.'::$token', $exception->getMessage());
        Assert::assertSame('initial-token', $command->token);
    }
}
