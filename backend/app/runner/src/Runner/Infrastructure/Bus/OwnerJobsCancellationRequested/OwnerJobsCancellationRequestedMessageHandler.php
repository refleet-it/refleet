<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Bus\OwnerJobsCancellationRequested;

use App\Runner\Runner\Application\Command\CancelOwnerJobs\CancelOwnerJobsCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class OwnerJobsCancellationRequestedMessageHandler
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(OwnerJobsCancellationRequestedMessage $message): void
    {
        $this->bus->dispatch(new CancelOwnerJobsCommand(
            ownerId: $message->ownerId,
            organizationId: $message->organizationId,
        ));
    }
}
