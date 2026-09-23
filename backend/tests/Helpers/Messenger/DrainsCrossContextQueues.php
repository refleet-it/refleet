<?php

declare(strict_types=1);

namespace App\Tests\Helpers\Messenger;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Cross-context messages travel over their own queues, so nothing acts on them inside a
 * request. Tests that assert on the receiving context's state call this to play the part
 * of the worker — including the encode/decode round-trip that maps the producer's message
 * class onto the consumer's own copy.
 */
trait DrainsCrossContextQueues
{
    private const array CROSS_CONTEXT_TRANSPORTS = [
        'to_identity',
        'to_organization',
        'to_runner',
        'to_qualification',
        'to_shift',
    ];

    protected function drainCrossContextQueues(int $maxRounds = 5): void
    {
        $container = static::getContainer();
        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);

        // A drained message can produce further ones (a job result completes a shift, which
        // notifies), so keep going until the queues stay empty.
        for ($round = 0; $round < $maxRounds; ++$round) {
            $handledAnything = false;

            foreach (self::CROSS_CONTEXT_TRANSPORTS as $transportName) {
                $transport = $container->get('messenger.transport.'.$transportName);
                \assert($transport instanceof TransportInterface);

                foreach ($transport->get() as $envelope) {
                    $bus->dispatch($envelope->with(new ReceivedStamp($transportName)));
                    $transport->ack($envelope);
                    $handledAnything = true;
                }
            }

            if (!$handledAnything) {
                return;
            }
        }
    }
}
