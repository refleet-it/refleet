<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Purger;

use Doctrine\Bundle\FixturesBundle\Purger\PurgerFactory;
use Doctrine\Common\DataFixtures\Purger\PurgerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @implements PurgerFactory<MultiEntityManagerPurger>
 */
final readonly class MultiEntityManagerPurgerFactory implements PurgerFactory
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
    ) {
    }

    #[\Override]
    public function createForEntityManager(
        ?string $emName,
        EntityManagerInterface $em,
        array $excluded = [],
        bool $purgeWithTruncate = false,
    ): PurgerInterface {
        return new MultiEntityManagerPurger($this->managerRegistry);
    }
}
