<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Command;

use App\Shared\Infrastructure\Command\PurgeDatabaseCommand;
use Doctrine\Common\DataFixtures\Purger\PurgerInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PurgeDatabaseCommand::class)]
final class PurgeDatabaseCommandTest extends TestCase
{
    #[Test]
    public function purges_database_and_returns_success_when_purger_completes(): void
    {
        // Arrange
        $purger = $this->createMock(PurgerInterface::class);
        $purger->expects($this->once())->method('purge');

        $command = new PurgeDatabaseCommand($purger);
        $tester = new CommandTester($command);

        // Act
        $statusCode = $tester->execute([]);

        // Assert
        Assert::assertSame(Command::SUCCESS, $statusCode);
        Assert::assertStringContainsString('Purging database...', $tester->getDisplay());
        Assert::assertStringContainsString('Database purged successfully!', $tester->getDisplay());
        Assert::assertStringNotContainsString('Failed to purge database:', $tester->getDisplay());
    }

    #[Test]
    public function returns_failure_and_prints_error_when_purger_throws_exception(): void
    {
        // Arrange
        $purger = $this->createMock(PurgerInterface::class);
        $purger->expects($this->once())->method('purge')->willThrowException(new \RuntimeException('connection is unavailable'));

        $command = new PurgeDatabaseCommand($purger);
        $tester = new CommandTester($command);

        // Act
        $statusCode = $tester->execute([]);

        // Assert
        Assert::assertSame(Command::FAILURE, $statusCode);
        Assert::assertStringContainsString('Purging database...', $tester->getDisplay());
        Assert::assertStringContainsString('Failed to purge database: connection is unavailable', $tester->getDisplay());
        Assert::assertStringNotContainsString('Database purged successfully!', $tester->getDisplay());
    }
}
