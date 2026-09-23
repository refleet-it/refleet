<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Application\Command\RequestPasswordReset;

use App\Identity\Account\Application\Command\RequestPasswordReset\RequestPasswordResetCommand;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestPasswordResetCommand::class)]
final class RequestPasswordResetCommandTest extends TestCase
{
    #[Test]
    public function creates_command_with_all_fields(): void
    {
        // Arrange
        $email = 'user@example.com';

        // Act
        $command = new RequestPasswordResetCommand(
            email: $email,
        );

        // Assert
        Assert::assertSame($email, $command->email);
    }
}
