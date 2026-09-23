<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\ResetPassword;

use App\Identity\Account\Application\Command\ResetPassword\ResetPasswordCommand;
use App\Tests\Helpers\TestCredentialsGenerator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResetPasswordCommand::class)]
final class ResetPasswordCommandTest extends TestCase
{
    #[Test]
    public function creates_command_with_all_fields(): void
    {
        // Arrange
        $token = 'reset-token-123';
        $newPassword = TestCredentialsGenerator::password();

        // Act
        $command = new ResetPasswordCommand(
            token: $token,
            newPassword: $newPassword,
        );

        // Assert
        Assert::assertSame($token, $command->token);
        Assert::assertSame($newPassword, $command->newPassword);
    }
}
