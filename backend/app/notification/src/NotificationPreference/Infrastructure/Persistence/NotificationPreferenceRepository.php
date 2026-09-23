<?php

declare(strict_types=1);

namespace App\Notification\NotificationPreference\Infrastructure\Persistence;

use App\Notification\NotificationPreference\Domain\Model\NotificationPreference;
use App\Notification\NotificationPreference\Domain\Repository\NotificationPreferenceRepositoryInterface;
use App\Shared\Domain\ValueObject\Id;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationPreference>
 */
class NotificationPreferenceRepository extends ServiceEntityRepository implements NotificationPreferenceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationPreference::class);
    }

    #[\Override]
    public function save(NotificationPreference $preference): void
    {
        $em = $this->getEntityManager();
        $em->persist($preference);
        $em->flush();
    }

    #[\Override]
    public function update(NotificationPreference $preference): void
    {
        $this->getEntityManager()->flush();
    }

    #[\Override]
    public function findById(Id $id): ?NotificationPreference
    {
        return $this->find($id->asString());
    }

    #[\Override]
    public function findByUserIdAndType(Id $userId, string $notificationType): ?NotificationPreference
    {
        return $this->findOneBy([
            'userId' => $userId->asString(),
            'notificationType' => $notificationType,
        ]);
    }

    /**
     * @return NotificationPreference[]
     */
    #[\Override]
    public function findByUserId(Id $userId): array
    {
        return $this->findBy(['userId' => $userId->asString()]);
    }
}
