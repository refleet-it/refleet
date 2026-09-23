<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\DomainListener\AccountUpdated;

use App\Identity\Account\Domain\Account\Event\AccountUpdated;
use App\Identity\Account\Infrastructure\Bus\AccountEmailChanged\AccountEmailChangedMessage;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'domain_event.bus')]
final readonly class PublishAccountEmailChangedMessage implements DomainEventListenerInterface
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(AccountUpdated $event): void
    {
        $this->bus->dispatch(new AccountEmailChangedMessage(
            accountId: $event->accountId->asString(),
            email: $event->email,
        ));
    }
}
