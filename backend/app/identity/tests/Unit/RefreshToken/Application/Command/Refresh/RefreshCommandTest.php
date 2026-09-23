<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RefreshToken\Application\Command\Refresh;

use App\Identity\RefreshToken\Application\Command\Refresh\RefreshCommand;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefreshCommand::class)]
final class RefreshCommandTest extends TestCase
{
    #[Test]
    public function creates_command_with_refresh_token(): void
    {
        // Arrange
        $refreshToken = 'refresh-token-123';

        // Act
        $command = new RefreshCommand(
            refreshToken: $refreshToken,
        );

        // Assert
        Assert::assertSame($refreshToken, $command->refreshToken);
    }
}
