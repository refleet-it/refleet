<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Persistence;

use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Infrastructure\Doctrine\QueryBuilderPaginator;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineQualificationTargetRepository implements QualificationTargetRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(QualificationTarget $target): void
    {
        $this->em->persist($target);
        $this->em->flush();
    }

    #[\Override]
    public function saveAll(array $targets): void
    {
        foreach ($targets as $target) {
            $this->em->persist($target);
        }

        $this->em->flush();
    }

    #[\Override]
    public function findById(QualificationTargetId $id): ?QualificationTarget
    {
        return $this->em->find(QualificationTarget::class, $id->asString());
    }

    #[\Override]
    public function findByQualificationId(QualificationId $qualificationId): array
    {
        return $this->em
            ->getRepository(QualificationTarget::class)
            ->findBy(['qualificationId' => $qualificationId->asString()], ['createdAt' => 'ASC']);
    }

    #[\Override]
    public function findByQualificationIdAndStatuses(QualificationId $qualificationId, array $statuses): array
    {
        return $this->em
            ->getRepository(QualificationTarget::class)
            ->findBy([
                'qualificationId' => $qualificationId->asString(),
                'status' => \array_map(static fn (QualificationTargetStatusEnum $status): string => $status->value, $statuses),
            ], ['createdAt' => 'ASC']);
    }

    #[\Override]
    public function getPaginatedListByQualificationId(QualificationId $qualificationId, PaginationParameters $pagination, ?QualificationTargetStatusEnum $status = null): ListResponse
    {
        $qb = $this->em
            ->getRepository(QualificationTarget::class)
            ->createQueryBuilder('t')
            ->where('t.qualificationId = :qualificationId')
            ->setParameter('qualificationId', $qualificationId->asString())
            ->orderBy('t.createdAt', 'ASC');

        if (null !== $status) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status->value);
        }

        /** @var QualificationTarget[] $items */
        [$items, $total] = QueryBuilderPaginator::paginate($qb, $pagination);

        return ListResponse::create($items, $total, $pagination);
    }

    #[\Override]
    public function findNonTerminalByQualificationId(QualificationId $qualificationId): array
    {
        return $this->findByQualificationIdAndStatuses($qualificationId, QualificationTargetStatusEnum::nonTerminalStatuses());
    }

    #[\Override]
    public function countByQualificationIdAndStatuses(QualificationId $qualificationId, array $statuses): int
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(t.id)')
            ->from(QualificationTarget::class, 't')
            ->where('t.qualificationId = :qualificationId')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('qualificationId', $qualificationId->asString())
            ->setParameter('statuses', \array_map(static fn (QualificationTargetStatusEnum $status): string => $status->value, $statuses));

        /** @var string|int $count */
        $count = $qb->getQuery()->getSingleScalarResult();

        return (int) $count;
    }

    #[\Override]
    public function statusBreakdown(QualificationId $qualificationId): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('t.status AS status', 'COUNT(t.id) AS cnt')
            ->from(QualificationTarget::class, 't')
            ->where('t.qualificationId = :qualificationId')
            ->setParameter('qualificationId', $qualificationId->asString())
            ->groupBy('t.status');

        /** @var array<int, array{status: QualificationTargetStatusEnum, cnt: string|int}> $rows */
        $rows = $qb->getQuery()->getResult();

        $breakdown = [];
        foreach ($rows as $row) {
            $breakdown[$row['status']->value] = (int) $row['cnt'];
        }

        return $breakdown;
    }
}
