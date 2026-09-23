<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Logger;

use App\Shared\Infrastructure\Logger\ConsoleActivitySubscriber;
use App\Tests\Helpers\Shared\Infrastructure\Logger\InMemoryLogger;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(ConsoleActivitySubscriber::class)]
final class ConsoleActivitySubscriberTest extends TestCase
{
    #[Test]
    public function logs_command_start_with_sanitized_arguments_and_options(): void
    {
        // Arrange
        $logger = new InMemoryLogger();
        $subscriber = new ConsoleActivitySubscriber($logger);
        $event = $this->createCommandEvent(
            'app:process',
            [
                'user' => 'john',
                'password' => 'top-secret',
                'token' => 'abc123',
                'authorization' => 'Bearer 123',
                'secret' => 's',
                'auth' => 'a',
            ],
            [
                '--limit' => 10,
                'password' => 'hidden',
            ],
        );

        // Act
        $subscriber($event);

        // Assert
        Assert::assertCount(1, $logger->records);
        Assert::assertSame('info', $logger->records[0]['level']);
        Assert::assertSame('console_command_start', $logger->records[0]['message']);
        Assert::assertSame('app:process', $logger->records[0]['context']['command']);
        Assert::assertSame('john', $logger->records[0]['context']['args']['user']);
        Assert::assertSame('***', $logger->records[0]['context']['args']['password']);
        Assert::assertSame('***', $logger->records[0]['context']['args']['token']);
        Assert::assertSame('***', $logger->records[0]['context']['args']['authorization']);
        Assert::assertSame('***', $logger->records[0]['context']['args']['secret']);
        Assert::assertSame('***', $logger->records[0]['context']['args']['auth']);
        Assert::assertSame(10, $logger->records[0]['context']['opts']['--limit']);
        Assert::assertSame('***', $logger->records[0]['context']['opts']['password']);
    }

    #[Test]
    public function logs_command_start_with_unknown_name_when_command_is_missing(): void
    {
        // Arrange
        $logger = new InMemoryLogger();
        $subscriber = new ConsoleActivitySubscriber($logger);
        $event = $this->createCommandEvent(null, ['password' => 'secret'], ['token' => 'abc']);

        // Act
        $subscriber($event);

        // Assert
        Assert::assertCount(1, $logger->records);
        Assert::assertSame('console_command_start', $logger->records[0]['message']);
        Assert::assertSame('unknown', $logger->records[0]['context']['command']);
        Assert::assertSame('***', $logger->records[0]['context']['args']['password']);
        Assert::assertSame('***', $logger->records[0]['context']['opts']['token']);
    }

    #[Test]
    public function logs_command_end_with_null_duration_when_start_is_missing(): void
    {
        // Arrange
        $logger = new InMemoryLogger();
        $subscriber = new ConsoleActivitySubscriber($logger);
        $event = $this->createTerminateEvent('app:missing', 7);

        // Act
        $subscriber($event);

        // Assert
        Assert::assertCount(1, $logger->records);
        Assert::assertSame('console_command_end', $logger->records[0]['message']);
        Assert::assertSame('app:missing', $logger->records[0]['context']['command']);
        Assert::assertSame(7, $logger->records[0]['context']['exit_code']);
        Assert::assertNull($logger->records[0]['context']['duration_ms']);
    }

    #[Test]
    public function clears_start_timestamp_after_first_terminate_event(): void
    {
        // Arrange
        $logger = new InMemoryLogger();
        $subscriber = new ConsoleActivitySubscriber($logger);
        $commandEvent = $this->createCommandEvent('app:job');
        $terminateEvent = $this->createTerminateEvent('app:job', 0);

        // Act
        $subscriber($commandEvent);
        $subscriber($terminateEvent);
        $subscriber($terminateEvent);

        // Assert
        Assert::assertCount(3, $logger->records);
        Assert::assertSame('console_command_end', $logger->records[1]['message']);
        Assert::assertIsInt($logger->records[1]['context']['duration_ms']);
        Assert::assertGreaterThanOrEqual(0, $logger->records[1]['context']['duration_ms']);
        Assert::assertSame('console_command_end', $logger->records[2]['message']);
        Assert::assertNull($logger->records[2]['context']['duration_ms']);
    }

    #[Test]
    public function ignores_unsupported_event_types(): void
    {
        // Arrange
        $logger = new InMemoryLogger();
        $subscriber = new ConsoleActivitySubscriber($logger);

        // Act
        $subscriber(new \stdClass());

        // Assert
        Assert::assertCount(0, $logger->records);
    }

    private function createCommandEvent(?string $commandName, array $arguments = [], array $options = []): ConsoleCommandEvent
    {
        $input = $this->createStub(InputInterface::class);
        $input->method('getArguments')->willReturn($arguments);
        $input->method('getOptions')->willReturn($options);
        $output = $this->createStub(OutputInterface::class);

        $command = null;
        if (null !== $commandName) {
            $command = $this->createStub(Command::class);
            $command->method('getName')->willReturn($commandName);
        }

        return new ConsoleCommandEvent($command, $input, $output);
    }

    private function createTerminateEvent(string $commandName, int $exitCode): ConsoleTerminateEvent
    {
        $input = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);
        $command = $this->createStub(Command::class);
        $command->method('getName')->willReturn($commandName);

        return new ConsoleTerminateEvent($command, $input, $output, $exitCode);
    }
}
