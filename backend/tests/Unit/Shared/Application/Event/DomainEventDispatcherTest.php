<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Event;

use App\File\File\Domain\Event\FileCreated;
use App\Fixtures\Factory\File\FileFactory;
use App\Fixtures\Factory\Identity\AccountFactory;
use App\Identity\Account\Domain\Account\Event\AccountCreated;
use App\Shared\Application\Event\DomainEventDispatcher;
use App\Shared\Domain\Event\DomainEventInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(DomainEventDispatcher::class)]
final class DomainEventDispatcherTest extends TestCase
{
    use Factories;

    #[Test]
    public function dispatches_domain_events_only_for_aggregate_roots_in_identity_map(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $file = FileFactory::new()->withoutPersisting()->create();
        $nonAggregate = new \stdClass();
        $dispatchedEvents = [];

        $domainEventBus = $this->createMock(MessageBusInterface::class);
        $domainEventBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatchedEvents): Envelope {
                $dispatchedEvents[] = $event;

                return new Envelope($event);
            });

        $dispatcher = new DomainEventDispatcher($domainEventBus);
        $args = $this->createPreFlushArgs([
            'accounts' => [$account],
            'misc' => [$nonAggregate],
            'files' => [$file],
        ]);

        // Act
        $dispatcher->preFlush($args);

        // Assert
        Assert::assertCount(2, $dispatchedEvents);
        Assert::assertInstanceOf(AccountCreated::class, $dispatchedEvents[0]);
        Assert::assertInstanceOf(FileCreated::class, $dispatchedEvents[1]);
        Assert::assertSame([], $account->getRecordedDomainEvents());
        Assert::assertSame([], $file->getRecordedDomainEvents());
    }

    #[Test]
    public function does_not_redispatch_events_after_they_have_been_consumed_by_first_pre_flush(): void
    {
        // Arrange
        $account = AccountFactory::new()->withoutPersisting()->create();
        $dispatchedEvents = [];

        $domainEventBus = $this->createMock(MessageBusInterface::class);
        $domainEventBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatchedEvents): Envelope {
                $dispatchedEvents[] = $event;

                return new Envelope($event);
            });

        $dispatcher = new DomainEventDispatcher($domainEventBus);
        $args = $this->createPreFlushArgs([
            'accounts' => [$account],
        ]);

        // Act
        $dispatcher->preFlush($args);
        $dispatcher->preFlush($args);

        // Assert
        Assert::assertCount(1, $dispatchedEvents);
        Assert::assertInstanceOf(DomainEventInterface::class, $dispatchedEvents[0]);
        Assert::assertSame([], $account->getRecordedDomainEvents());
    }

    private function createPreFlushArgs(array $identityMap): PreFlushEventArgs
    {
        $uow = $this->createStub(UnitOfWork::class);
        $uow->method('getIdentityMap')->willReturn($identityMap);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($uow);

        return new PreFlushEventArgs($entityManager);
    }
}
