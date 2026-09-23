<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Event;

use App\File\File\Domain\Event\FileCreated;
use App\Identity\Account\Domain\Account\Event\PasswordResetRequested;
use App\Notification\Notification\Domain\Event\NotificationCreated;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Event\DomainEventInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DomainEventInterfaceTest extends TestCase
{
    #[Test]
    public function interface_is_defined_and_public(): void
    {
        Assert::assertTrue(\interface_exists(DomainEventInterface::class));

        $ref = new \ReflectionClass(DomainEventInterface::class);
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal()); // userland interface
    }

    #[Test]
    public function interface_has_no_methods(): void
    {
        $ref = new \ReflectionClass(DomainEventInterface::class);
        Assert::assertCount(0, $ref->getMethods());
    }

    #[Test]
    public function several_events_implement_the_interface(): void
    {
        $implementations = [
            DomainEvent::class,
            FileCreated::class,
            NotificationCreated::class,
            PasswordResetRequested::class,
        ];

        foreach ($implementations as $class) {
            Assert::assertTrue(\is_subclass_of($class, DomainEventInterface::class));
        }
    }
}
