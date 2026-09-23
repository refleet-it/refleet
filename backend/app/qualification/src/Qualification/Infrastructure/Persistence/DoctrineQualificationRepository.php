<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Persistence;

use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;
use App\Shared\Infrastructure\Doctrine\QueryBuilderPaginator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineQualificationRepository implements QualificationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(Qualification $qualification): void
    {
        $this->em->persist($qualification);
        $this->em->flush();
    }

    #[\Override]
    public function findById(QualificationId $id): ?Qualification
    {
        return $this->em->find(Qualification::class, $id->asString());
    }

    #[\Override]
    public function findByIdForOrganization(QualificationId $id, OrganizationId $organizationId): ?Qualification
    {
        /** @var Qualification|null $qualification */
        $qualification = $this->em
            ->getRepository(Qualification::class)
            ->findOneBy(['id' => $id->asString(), 'organizationId' => $organizationId->asString()]);

        return $qualification;
    }

    #[\Override]
    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        return $this->em
            ->getRepository(Qualification::class)
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
        $qb->andWhere($archived ? 'q.archivedAt IS NOT NULL' : 'q.archivedAt IS NULL');

        /** @var Qualification[] $items */
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
            ->getRepository(Qualification::class)
            ->createQueryBuilder('q')
            ->where('q.organizationId = :organizationId')
            ->setParameter('organizationId', $organizationId->asString());

        if (null !== $search && '' !== $search) {
            $qb->andWhere('LOWER(q.title) LIKE LOWER(:search)')->setParameter('search', '%'.$search.'%');
        }

        if (null !== $status && '' !== $status) {
            $qb->andWhere('q.status = :status')->setParameter('status', $status);
        }

        if (null !== $sorting) {
            match ($sorting->getField()) {
                'title' => $qb->orderBy('q.title', $sorting->getDirection()->value),
                'status' => $qb->orderBy('q.status', $sorting->getDirection()->value),
                'createdAt' => $qb->orderBy('q.createdAt', $sorting->getDirection()->value),
                default => $qb->orderBy('q.id', 'DESC'),
            };
        } else {
            $qb->orderBy('q.id', 'DESC');
        }

        return $qb;
    }
}
