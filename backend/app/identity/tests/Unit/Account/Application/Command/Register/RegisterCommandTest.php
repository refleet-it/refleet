<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\Register;

use App\Identity\Account\Application\Command\Register\RegisterCommand;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegisterCommand::class)]
final class RegisterCommandTest extends TestCase
{
    #[Test]
    public function creates_command_with_email_password_and_role(): void
    {
        // Arrange
        $email = 'new.user@example.com';
        $plainPassword = 'PlainPassword1!';
        $role = RoleEnum::USER;

        // Act
        $command = new RegisterCommand(
            email: $email,
            plainPassword: $plainPassword,
            role: $role,
            termsAccepted: true,
        );

        // Assert
        Assert::assertSame($email, $command->email);
        Assert::assertSame($plainPassword, $command->plainPassword);
        Assert::assertSame($role, $command->role);
        Assert::assertTrue($command->termsAccepted);
    }
}
