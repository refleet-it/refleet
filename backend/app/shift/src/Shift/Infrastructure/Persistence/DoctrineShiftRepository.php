<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;
use App\Shared\Infrastructure\Doctrine\QueryBuilderPaginator;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineShiftRepository implements ShiftRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(Shift $shift): void
    {
        $this->em->persist($shift);
        $this->em->flush();
    }

    #[\Override]
    public function findById(ShiftId $id): ?Shift
    {
        return $this->em->find(Shift::class, $id->asString());
    }

    #[\Override]
    public function findByIdForOrganization(ShiftId $id, OrganizationId $organizationId): ?Shift
    {
        /** @var Shift|null $shift */
        $shift = $this->em
            ->getRepository(Shift::class)
            ->findOneBy(['id' => $id->asString(), 'organizationId' => $organizationId->asString()]);

        return $shift;
    }

    #[\Override]
    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        return $this->em
            ->getRepository(Shift::class)
            ->findBy(['organizationId' => $organizationId->asString()], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    #[\Override]
    public function getPaginatedList(
        OrganizationId $organizationId,
        PaginationParameters $pagination,
        ?SortParameters $sorting,
        ?string $search = null,
        ?string $status = null,
        bool $archived = false,
    ): ListResponse {
        $qb = $this->buildQueryBuilder($organizationId, $sorting, $search, $status);
        $qb->andWhere($archived ? 's.archivedAt IS NOT NULL' : 's.archivedAt IS NULL');

        /** @var Shift[] $items */
        [$items, $total] = QueryBuilderPaginator::paginate($qb, $pagination);

        return ListResponse::create($items, $total, $pagination);
    }

    private function buildQueryBuilder(
        OrganizationId $organizationId,
        ?SortParameters $sorting,
        ?string $search = null,
        ?string $status = null,
    ): QueryBuilder {
        $qb = $this->em
            ->getRepository(Shift::class)
            ->createQueryBuilder('s')
            ->where('s.organizationId = :organizationId')
            ->setParameter('organizationId', $organizationId->asString());

        if (null !== $search && '' !== $search) {
            $qb->andWhere('LOWER(s.title) LIKE LOWER(:search)')->setParameter('search', '%'.$search.'%');
        }

        if (null !== $status && '' !== $status) {
            $qb->andWhere('s.status = :status')->setParameter('status', $status);
        }

        if (null !== $sorting) {
            match ($sorting->getField()) {
                'title' => $qb->orderBy('s.title', $sorting->getDirection()->value),
                'status' => $qb->orderBy('s.status', $sorting->getDirection()->value),
                'createdAt' => $qb->orderBy('s.createdAt', $sorting->getDirection()->value),
                default => $qb->orderBy('s.id', 'DESC'),
            };
        } else {
            $qb->orderBy('s.id', 'DESC');
        }

        return $qb;
    }
}
