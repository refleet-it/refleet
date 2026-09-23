<?php

declare(strict_types=1);

namespace App\Shared\Application\Command\Sync;

interface CommandBusInterface
{
    /**
     * @template T of object
     *
     * @param T $command
     */
    public function dispatch(object $command): void;
}
