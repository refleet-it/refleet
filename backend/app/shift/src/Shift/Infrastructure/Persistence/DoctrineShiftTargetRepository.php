<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Infrastructure\Doctrine\QueryBuilderPaginator;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineShiftTargetRepository implements ShiftTargetRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(ShiftTarget $target): void
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
    public function findById(ShiftTargetId $id): ?ShiftTarget
    {
        return $this->em->find(ShiftTarget::class, $id->asString());
    }

    #[\Override]
    public function findByMergeRequestUrl(string $mergeRequestUrl): ?ShiftTarget
    {
        return $this->em
            ->getRepository(ShiftTarget::class)
            ->findOneBy(['mergeRequestUrl' => $mergeRequestUrl]);
    }

    #[\Override]
    public function getPaginatedListByShiftId(ShiftId $shiftId, PaginationParameters $pagination, ?ShiftTargetStatusEnum $status = null): ListResponse
    {
        $qb = $this->em
            ->getRepository(ShiftTarget::class)
            ->createQueryBuilder('t')
            ->where('t.shiftId = :shiftId')
            ->setParameter('shiftId', $shiftId->asString())
            ->orderBy('t.createdAt', 'ASC');

        if (null !== $status) {
            $qb->andWhere('t.status = :status')->setParameter('status', $status->value);
        }

        /** @var ShiftTarget[] $items */
        [$items, $total] = QueryBuilderPaginator::paginate($qb, $pagination);

        return ListResponse::create($items, $total, $pagination);
    }

    #[\Override]
    public function findByShiftIdAndStatuses(ShiftId $shiftId, array $statuses): array
    {
        return $this->em
            ->getRepository(ShiftTarget::class)
            ->findBy([
                'shiftId' => $shiftId->asString(),
                'status' => \array_map(static fn (ShiftTargetStatusEnum $status): string => $status->value, $statuses),
            ], ['createdAt' => 'ASC']);
    }

    #[\Override]
    public function findNonTerminalByShiftId(ShiftId $shiftId): array
    {
        $nonTerminal = \array_filter(
            ShiftTargetStatusEnum::cases(),
            static fn (ShiftTargetStatusEnum $status): bool => !$status->isTerminal(),
        );

        return $this->findByShiftIdAndStatuses($shiftId, \array_values($nonTerminal));
    }

    #[\Override]
    public function countByShiftIdAndStatuses(ShiftId $shiftId, array $statuses): int
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(t.id)')
            ->from(ShiftTarget::class, 't')
            ->where('t.shiftId = :shiftId')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('shiftId', $shiftId->asString())
            ->setParameter('statuses', \array_map(static fn (ShiftTargetStatusEnum $status): string => $status->value, $statuses));

        /** @var string|int $count */
        $count = $qb->getQuery()->getSingleScalarResult();

        return (int) $count;
    }

    #[\Override]
    public function statusBreakdown(ShiftId $shiftId): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('t.status AS status', 'COUNT(t.id) AS cnt')
            ->from(ShiftTarget::class, 't')
            ->where('t.shiftId = :shiftId')
            ->setParameter('shiftId', $shiftId->asString())
            ->groupBy('t.status');

        /** @var array<int, array{status: ShiftTargetStatusEnum, cnt: string|int}> $rows */
        $rows = $qb->getQuery()->getResult();

        $breakdown = [];
        foreach ($rows as $row) {
            $breakdown[$row['status']->value] = (int) $row['cnt'];
        }

        return $breakdown;
    }
}
