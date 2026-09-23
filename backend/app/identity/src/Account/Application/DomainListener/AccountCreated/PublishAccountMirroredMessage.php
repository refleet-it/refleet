<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\DomainListener\AccountCreated;

use App\Identity\Account\Domain\Account\Event\AccountCreated;
use App\Identity\Account\Infrastructure\Bus\AccountMirrored\AccountMirroredMessage;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'domain_event.bus')]
final readonly class PublishAccountMirroredMessage implements DomainEventListenerInterface
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(AccountCreated $event): void
    {
        $this->bus->dispatch(new AccountMirroredMessage(
            accountId: $event->accountId()->asString(),
            email: $event->email(),
        ));
    }
}
