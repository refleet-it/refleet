<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * Touches a heartbeat file on every worker loop iteration so the Docker healthcheck
 * can detect a hung `messenger:consume` process (stuck message, deadlock) instead of
 * the previous "exit(0)" no-op check, which reported healthy unconditionally.
 */
#[AsEventListener(event: WorkerRunningEvent::class)]
final readonly class WorkerHeartbeatListener
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/worker-heartbeat')]
        private string $heartbeatFilePath,
    ) {
    }

    public function __invoke(WorkerRunningEvent $event): void
    {
        \touch($this->heartbeatFilePath);
    }
}
