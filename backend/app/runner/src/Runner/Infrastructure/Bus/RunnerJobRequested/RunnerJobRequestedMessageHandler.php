<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Bus\RunnerJobRequested;

use App\Runner\Runner\Application\Command\EnqueueRunnerJob\EnqueueRunnerJobCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class RunnerJobRequestedMessageHandler
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(RunnerJobRequestedMessage $message): void
    {
        $this->bus->dispatch(new EnqueueRunnerJobCommand(
            jobId: $message->jobId,
            ownerId: $message->ownerId,
            ownerTargetId: $message->ownerTargetId,
            ownerLabel: $message->ownerLabel,
            organizationId: $message->organizationId,
            kind: $message->kind,
            mode: $message->mode,
            payload: $message->payload,
            engine: $message->engine,
        ));
    }
}
