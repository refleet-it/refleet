<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Event;

use App\Shared\Domain\Event\AggregateRoot;
use App\Shared\Domain\Event\DomainEventInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AggregateRoot::class)]
final class AggregateRootTest extends TestCase
{
    #[Test]
    public function returns_empty_events_when_none_recorded(): void
    {
        $aggregate = new class extends AggregateRoot {
            public function trigger(DomainEventInterface $event): void
            {
                $this->recordThat($event);
            }
        };

        $events = $aggregate->getRecordedDomainEvents();

        Assert::assertSame([], $events);
    }

    #[Test]
    public function collects_and_clears_recorded_domain_events(): void
    {
        $aggregate = new class extends AggregateRoot {
            public function trigger(DomainEventInterface $event): void
            {
                $this->recordThat($event);
            }
        };

        $event1 = new class implements DomainEventInterface {};
        $event2 = new class implements DomainEventInterface {};

        $aggregate->trigger($event1);
        $aggregate->trigger($event2);

        $events = $aggregate->getRecordedDomainEvents();
        Assert::assertCount(2, $events);
        Assert::assertSame([$event1, $event2], $events);

        $eventsAfterClear = $aggregate->getRecordedDomainEvents();
        Assert::assertSame([], $eventsAfterClear);

        $event3 = new class implements DomainEventInterface {};
        $aggregate->trigger($event3);

        $eventsNew = $aggregate->getRecordedDomainEvents();
        Assert::assertSame([$event3], $eventsNew);
    }
}
