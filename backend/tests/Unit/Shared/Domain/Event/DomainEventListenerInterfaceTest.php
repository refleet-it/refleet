<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Event;

use App\Identity\Account\Application\DomainListener\PasswordResetCompleted\SendPasswordResetConfirmationEmail;
use App\Identity\Account\Application\DomainListener\PasswordResetRequested\SendPasswordResetEmail;
use App\Shared\Domain\Event\DomainEventListenerInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DomainEventListenerInterfaceTest extends TestCase
{
    #[Test]
    public function interface_is_defined_and_public(): void
    {
        Assert::assertTrue(\interface_exists(DomainEventListenerInterface::class));

        $ref = new \ReflectionClass(DomainEventListenerInterface::class);
        Assert::assertTrue($ref->isInterface());
        Assert::assertFalse($ref->isInternal()); // userland interface
    }

    #[Test]
    public function interface_has_no_methods(): void
    {
        $ref = new \ReflectionClass(DomainEventListenerInterface::class);
        Assert::assertCount(0, $ref->getMethods());
    }

    #[Test]
    public function interface_has_no_constants(): void
    {
        $ref = new \ReflectionClass(DomainEventListenerInterface::class);
        Assert::assertCount(0, $ref->getConstants(), 'Marker interfaces should not expose constants.');
    }

    #[Test]
    #[DataProvider('listenerClassProvider')]
    public function known_listeners_implement_the_interface(string $class): void
    {
        Assert::assertTrue(\is_subclass_of($class, DomainEventListenerInterface::class));
    }

    /**
     * @return iterable<string[]>
     */
    public static function listenerClassProvider(): iterable
    {
        yield 'password reset requested email' => [SendPasswordResetEmail::class];
        yield 'password reset completed email' => [SendPasswordResetConfirmationEmail::class];
    }
}
