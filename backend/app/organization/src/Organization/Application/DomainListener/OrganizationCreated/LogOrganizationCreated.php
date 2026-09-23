<?php

declare(strict_types=1);

namespace App\Organization\Organization\Application\DomainListener\OrganizationCreated;

use App\Organization\Organization\Domain\Organization\Event\OrganizationCreated;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'domain_event.bus')]
final readonly class LogOrganizationCreated implements DomainEventListenerInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(OrganizationCreated $event): void
    {
        $this->logger->info('Organization created', [
            'organizationId' => $event->organizationId->asString(),
            'name' => $event->name,
        ]);
    }
}
