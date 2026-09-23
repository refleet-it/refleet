<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Domain\Event\AggregateRoot;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Not tagged as a doctrine.event_listener here: a standalone app only has its own
 * connection registered, so a fixed set of eight `connection: '<context>'` attributes (as
 * this class used to carry) fails to boot for the other seven. Each app's own
 * config/services.yaml tags this service for its own connection instead; the monolith's
 * config/services.yaml re-declares it with all eight, since it alone needs every one at
 * once — see docs/adr/0001-multiple-kernels.md.
 */
final readonly class DomainEventDispatcher
{
    public function __construct(
        #[Autowire(service: 'domain_event.bus')]
        private MessageBusInterface $domainEventBus,
    ) {
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if ($entity instanceof AggregateRoot) {
                    foreach ($entity->getRecordedDomainEvents() as $event) {
                        $this->domainEventBus->dispatch($event);
                    }
                }
            }
        }
    }
}
