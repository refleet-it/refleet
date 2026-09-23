<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Listener;

use App\Shared\Domain\Exception\AppException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[AsEventListener(priority: 200)]
final class UnwrapAppExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if ($throwable instanceof HandlerFailedException) {
            $previous = $throwable->getPrevious();

            if (null !== $previous && $previous instanceof AppException) {
                $event->setThrowable($previous);
            }
        }
    }
}
