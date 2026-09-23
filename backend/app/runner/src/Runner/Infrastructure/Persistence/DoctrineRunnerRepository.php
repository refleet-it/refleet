<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Persistence;

use App\Runner\Runner\Domain\Runner\Model\Runner;
use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;
use App\Shared\Infrastructure\Doctrine\QueryBuilderPaginator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineRunnerRepository implements RunnerRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(Runner $runner): void
    {
        $this->em->persist($runner);
        $this->em->flush();
    }

    #[\Override]
    public function findById(RunnerId $id): ?Runner
    {
        return $this->em->find(Runner::class, $id->asString());
    }

    #[\Override]
    public function findByIdForOrganization(RunnerId $id, OrganizationId $organizationId): ?Runner
    {
        /** @var Runner|null $runner */
        $runner = $this->em
            ->getRepository(Runner::class)
            ->findOneBy(['id' => $id->asString(), 'organizationId' => $organizationId->asString()]);

        return $runner;
    }

    #[\Override]
    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        return $this->em
            ->getRepository(Runner::class)
            ->findBy(['organizationId' => $organizationId->asString()], ['name' => 'ASC']);
    }

    #[\Override]
    public function getPaginatedList(OrganizationId $organizationId, PaginationParameters $pagination, ?SortParameters $sorting, bool $archived = false): ListResponse
    {
        $qb = $this->buildQueryBuilder($organizationId, $sorting);
        $qb->andWhere($archived ? 'r.archivedAt IS NOT NULL' : 'r.archivedAt IS NULL');

        /** @var Runner[] $items */
        [$items, $total] = QueryBuilderPaginator::paginate($qb, $pagination);

        return ListResponse::create($items, $total, $pagination);
    }

    #[\Override]
    public function findByOrganizationIdAndName(OrganizationId $organizationId, string $name): ?Runner
    {
        return $this->em
            ->getRepository(Runner::class)
            ->findOneBy(['organizationId' => $organizationId->asString(), 'name' => $name, 'archivedAt' => null]);
    }

    private function buildQueryBuilder(OrganizationId $organizationId, ?SortParameters $sorting): QueryBuilder
    {
        $qb = $this->em
            ->getRepository(Runner::class)
            ->createQueryBuilder('r')
            ->where('r.organizationId = :organizationId')
            ->setParameter('organizationId', $organizationId->asString());

        if (null !== $sorting) {
            match ($sorting->getField()) {
                'name' => $qb->orderBy('r.name', $sorting->getDirection()->value),
                'createdAt' => $qb->orderBy('r.createdAt', $sorting->getDirection()->value),
                'lastSeenAt' => $qb->orderBy('r.lastSeenAt', $sorting->getDirection()->value),
                default => $qb->orderBy('r.id', 'DESC'),
            };
        } else {
            $qb->orderBy('r.id', 'DESC');
        }

        return $qb;
    }
}
