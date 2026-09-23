<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\DomainListener\AccountCreated;

use App\Identity\Account\Domain\Account\Event\AccountCreated;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'domain_event.bus')]
final readonly class LogAccountCreated implements DomainEventListenerInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AccountCreated $event): void
    {
        $this->logger->info('Account created', [
            'accountId' => $event->accountId()->asString(),
            'email' => $event->email(),
        ]);
    }
}
