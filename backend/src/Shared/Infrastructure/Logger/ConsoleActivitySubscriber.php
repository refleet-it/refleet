<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logger;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'console.command')]
#[AsEventListener(event: 'console.terminate')]
final class ConsoleActivitySubscriber
{
    /** @var array<string, float> */
    private array $starts = [];

    public function __construct(
        #[Autowire(service: 'monolog.logger.console')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(object $event): void
    {
        if ($event instanceof ConsoleCommandEvent) {
            $command = $event->getCommand();
            $input = $event->getInput();
            $name = $command?->getName() ?? 'unknown';

            $this->starts[$name] = \microtime(true);

            $this->logger->info('console_command_start', [
                'command' => $name,
                'args' => $this->sanitize($input->getArguments()),
                'opts' => $this->sanitize($input->getOptions()),
            ]);

            return;
        }

        if ($event instanceof ConsoleTerminateEvent) {
            $command = $event->getCommand();
            $name = $command?->getName() ?? 'unknown';
            $start = $this->starts[$name] ?? null;
            $durationMs = null;
            if (\is_float($start)) {
                $durationMs = (int) \round((\microtime(true) - $start) * 1000);
                unset($this->starts[$name]);
            }

            $this->logger->info('console_command_end', [
                'command' => $name,
                'exit_code' => $event->getExitCode(),
                'duration_ms' => $durationMs,
            ]);

            return;
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        $secrets = ['password', 'token', 'authorization', 'auth', 'secret'];
        foreach ($secrets as $key) {
            if (\array_key_exists($key, $data)) {
                $data[$key] = '***';
            }
        }

        return $data;
    }
}
