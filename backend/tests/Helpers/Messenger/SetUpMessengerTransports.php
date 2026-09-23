<?php

declare(strict_types=1);

namespace App\Tests\Helpers\Messenger;

use Symfony\Component\Messenger\Transport\SetupableTransportInterface;

/**
 * Foundry drops the whole test database once per run and rebuilds it from entity metadata, which
 * leaves out messenger_messages — it is a transport table, not an entity. The doctrine transport
 * would normally create it on first use, but by then DAMA has the test inside a transaction, and
 * the failed INSERT that triggers the auto-setup has already aborted it, so every later statement
 * dies with SQLSTATE[25P02].
 *
 * Registering this as Foundry global state runs it in the one window where that is fixable: after
 * the schema has been rebuilt and while DamaDatabaseResetter still has static connections off.
 *
 * @see \Zenstruck\Foundry\ORM\ResetDatabase\DamaDatabaseResetter
 */
final readonly class SetUpMessengerTransports
{
    /**
     * @param iterable<object> $transports
     */
    public function __construct(private iterable $transports)
    {
    }

    public function __invoke(): void
    {
        foreach ($this->transports as $transport) {
            if ($transport instanceof SetupableTransportInterface) {
                $transport->setup();
            }
        }
    }
}
