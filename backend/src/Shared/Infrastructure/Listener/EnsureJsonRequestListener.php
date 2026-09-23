<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Listener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(event: 'kernel.request', priority: 2049)]
final readonly class EnsureJsonRequestListener
{
    public function __construct()
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->headers->has('Content-Type')) {
            $request->headers->set('Content-Type', 'application/json');
        }
    }
}
