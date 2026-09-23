<?php

declare(strict_types=1);

namespace App\Notification\Notification\Infrastructure\Persistence;

use App\Notification\Notification\Domain\Model\Notification;
use App\Notification\Notification\Domain\Repository\NotificationRepositoryInterface;
use App\Shared\Domain\ValueObject\Id;
use Doctrine\ORM\EntityManagerInterface;

final readonly class NotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[\Override]
    public function save(Notification $notification): void
    {
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    #[\Override]
    public function update(Notification $notification): void
    {
        $this->entityManager->flush();
    }

    #[\Override]
    public function findById(Id $id): ?Notification
    {
        return $this->entityManager->find(Notification::class, $id->asString());
    }

    /**
     * @return Notification[]
     */
    #[\Override]
    public function findPendingByType(string $type): array
    {
        /** @var Notification[] $result */
        $result = $this->entityManager->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->where('n.status = :status')
            ->andWhere('n.type = :type')
            ->setParameter('status', 'pending')
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
