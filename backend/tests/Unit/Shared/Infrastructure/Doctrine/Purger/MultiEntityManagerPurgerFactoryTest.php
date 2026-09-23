<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Doctrine\Purger;

use App\Shared\Infrastructure\Doctrine\Purger\MultiEntityManagerPurger;
use App\Shared\Infrastructure\Doctrine\Purger\MultiEntityManagerPurgerFactory;
use Doctrine\Common\DataFixtures\Purger\PurgerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MultiEntityManagerPurgerFactory::class)]
final class MultiEntityManagerPurgerFactoryTest extends TestCase
{
    #[Test]
    public function create_for_entity_manager_returns_multi_entity_manager_purger_with_same_registry(): void
    {
        // Arrange
        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $factory = new MultiEntityManagerPurgerFactory($managerRegistry);

        // Act
        $purger = $factory->createForEntityManager('payments', $entityManager, ['audit_log'], true);

        // Assert
        Assert::assertInstanceOf(PurgerInterface::class, $purger);
        Assert::assertInstanceOf(MultiEntityManagerPurger::class, $purger);

        $reflection = new \ReflectionObject($purger);
        $property = $reflection->getProperty('managerRegistry');
        Assert::assertSame($managerRegistry, $property->getValue($purger));
    }

    #[Test]
    public function create_for_entity_manager_returns_new_instance_on_each_call_even_for_different_arguments(): void
    {
        // Arrange
        $managerRegistry = $this->createStub(ManagerRegistry::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $factory = new MultiEntityManagerPurgerFactory($managerRegistry);

        // Act
        $firstPurger = $factory->createForEntityManager(null, $entityManager);
        $secondPurger = $factory->createForEntityManager('business', $entityManager, ['users'], false);

        // Assert
        Assert::assertNotSame($firstPurger, $secondPurger);
        Assert::assertInstanceOf(MultiEntityManagerPurger::class, $firstPurger);
        Assert::assertInstanceOf(MultiEntityManagerPurger::class, $secondPurger);
    }
}
