<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\Login;

use App\Identity\Account\Application\Command\Login\LoginCommand;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoginCommand::class)]
final class LoginCommandTest extends TestCase
{
    #[Test]
    public function creates_command_with_email_and_plain_password(): void
    {
        // Arrange
        $email = 'user@example.com';
        $plainPassword = 'SecretPassword1!';

        // Act
        $command = new LoginCommand(
            email: $email,
            plainPassword: $plainPassword,
        );

        // Assert
        Assert::assertSame($email, $command->email);
        Assert::assertSame($plainPassword, $command->plainPassword);
    }
}
