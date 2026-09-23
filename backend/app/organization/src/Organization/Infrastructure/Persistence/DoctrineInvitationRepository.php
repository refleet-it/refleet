<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Persistence;

use App\Organization\Organization\Domain\Invitation\Model\Invitation;
use App\Organization\Organization\Domain\Invitation\Repository\InvitationRepositoryInterface;
use App\Organization\Organization\Domain\Invitation\ValueObject\InvitationId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;
use App\Shared\Infrastructure\Doctrine\QueryBuilderPaginator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineInvitationRepository implements InvitationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(Invitation $invitation): void
    {
        $this->em->persist($invitation);
        $this->em->flush();
    }

    #[\Override]
    public function findById(InvitationId $id): ?Invitation
    {
        return $this->em->find(Invitation::class, $id->asString());
    }

    #[\Override]
    public function findByToken(string $token): ?Invitation
    {
        return $this->em
            ->getRepository(Invitation::class)
            ->findOneBy(['token' => $token]);
    }

    #[\Override]
    public function getPendingPaginatedList(OrganizationId $organizationId, PaginationParameters $pagination, ?SortParameters $sorting): ListResponse
    {
        $qb = $this->pendingQueryBuilder()
            ->andWhere('i.organizationId = :organizationId')
            ->setParameter('organizationId', $organizationId->asString());

        match ($sorting?->getField()) {
            'email' => $qb->orderBy('i.email', $sorting->getDirection()->value),
            'expiresAt' => $qb->orderBy('i.expiresAt', $sorting->getDirection()->value),
            'createdAt' => $qb->orderBy('i.createdAt', $sorting->getDirection()->value),
            default => $qb->orderBy('i.createdAt', 'DESC'),
        };

        /** @var Invitation[] $items */
        [$items, $total] = QueryBuilderPaginator::paginate($qb, $pagination);

        return ListResponse::create($items, $total, $pagination);
    }

    #[\Override]
    public function findPendingByOrganizationIdAndEmail(OrganizationId $organizationId, string $email): ?Invitation
    {
        /** @var Invitation|null $result */
        $result = $this->pendingQueryBuilder()
            ->andWhere('i.organizationId = :organizationId')
            ->andWhere('i.email = :email')
            ->setParameter('organizationId', $organizationId->asString())
            ->setParameter('email', \mb_strtolower($email))
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    private function pendingQueryBuilder(): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select('i')
            ->from(Invitation::class, 'i')
            ->andWhere('i.acceptedAt IS NULL')
            ->andWhere('i.cancelledAt IS NULL')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('now', new \DateTimeImmutable());
    }
}
