<?php

declare(strict_types=1);

namespace App\Project\Project\Infrastructure\Persistence;

use App\Project\Project\Domain\Project\Model\Project;
use App\Project\Project\Domain\Project\Repository\ProjectRepositoryInterface;
use App\Project\Project\Domain\Project\ValueObject\GitLabProjectId;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Project\Project\Domain\Project\ValueObject\ProjectId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;
use App\Shared\Infrastructure\Doctrine\QueryBuilderPaginator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineProjectRepository implements ProjectRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(Project $project): void
    {
        $this->em->persist($project);
        $this->em->flush();
    }

    #[\Override]
    public function findById(ProjectId $id): ?Project
    {
        return $this->em->find(Project::class, $id->asString());
    }

    #[\Override]
    public function findByExternalId(OrganizationId $organizationId, GitLabProjectId $externalId): ?Project
    {
        return $this->em
            ->getRepository(Project::class)
            ->findOneBy([
                'organizationId' => $organizationId->asString(),
                'externalId' => $externalId->asString(),
            ]);
    }

    #[\Override]
    public function findActiveByOrganizationId(OrganizationId $organizationId): array
    {
        return $this->em
            ->getRepository(Project::class)
            ->findBy(['organizationId' => $organizationId->asString(), 'archivedAt' => null]);
    }

    #[\Override]
    public function findByIds(OrganizationId $organizationId, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->em
            ->getRepository(Project::class)
            ->findBy([
                'organizationId' => $organizationId->asString(),
                'id' => \array_map(static fn (ProjectId $id): string => $id->asString(), $ids),
            ]);
    }

    #[\Override]
    public function getPaginatedList(OrganizationId $organizationId, PaginationParameters $pagination, ?SortParameters $sorting): ListResponse
    {
        $qb = $this->buildQueryBuilder($organizationId, $sorting);

        /** @var Project[] $items */
        [$items, $total] = QueryBuilderPaginator::paginate($qb, $pagination);

        return ListResponse::create($items, $total, $pagination);
    }

    private function buildQueryBuilder(OrganizationId $organizationId, ?SortParameters $sorting): QueryBuilder
    {
        $qb = $this->em
            ->getRepository(Project::class)
            ->createQueryBuilder('p')
            ->where('p.organizationId = :organizationId')
            ->andWhere('p.archivedAt IS NULL')
            ->setParameter('organizationId', $organizationId->asString());

        if (null !== $sorting) {
            match ($sorting->getField()) {
                'name' => $qb->orderBy('p.name', $sorting->getDirection()->value),
                'createdAt' => $qb->orderBy('p.createdAt', $sorting->getDirection()->value),
                'lastSyncedAt' => $qb->orderBy('p.lastSyncedAt', $sorting->getDirection()->value),
                default => $qb->orderBy('p.id', 'DESC'),
            };
        } else {
            $qb->orderBy('p.id', 'DESC');
        }

        return $qb;
    }
}
