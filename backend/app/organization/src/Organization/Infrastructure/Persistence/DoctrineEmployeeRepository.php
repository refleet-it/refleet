<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Persistence;

use App\Organization\Organization\Domain\Employee\Model\Employee;
use App\Organization\Organization\Domain\Employee\Repository\EmployeeRepositoryInterface;
use App\Organization\Organization\Domain\Employee\ValueObject\AccountId;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;
use App\Shared\Infrastructure\Doctrine\QueryBuilderPaginator;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineEmployeeRepository implements EmployeeRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(Employee $employee): void
    {
        $this->em->persist($employee);
        $this->em->flush();
    }

    #[\Override]
    public function findByAccountId(AccountId $accountId): ?Employee
    {
        return $this->em->find(Employee::class, $accountId->asString());
    }

    #[\Override]
    public function findByEmail(string $email): ?Employee
    {
        return $this->em
            ->getRepository(Employee::class)
            ->findOneBy(['email' => \mb_strtolower($email)]);
    }

    #[\Override]
    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        return $this->em
            ->getRepository(Employee::class)
            ->findBy(['organizationId' => $organizationId->asString()]);
    }

    #[\Override]
    public function getPaginatedList(OrganizationId $organizationId, PaginationParameters $pagination, ?SortParameters $sorting): ListResponse
    {
        $qb = $this->em
            ->getRepository(Employee::class)
            ->createQueryBuilder('e')
            ->where('e.organizationId = :organizationId')
            ->setParameter('organizationId', $organizationId->asString());

        if (null !== $sorting) {
            match ($sorting->getField()) {
                'email' => $qb->orderBy('e.email', $sorting->getDirection()->value),
                'role' => $qb->orderBy('e.role', $sorting->getDirection()->value),
                'joinedOrganizationAt' => $qb->orderBy('e.joinedOrganizationAt', $sorting->getDirection()->value),
                default => $qb->orderBy('e.email', 'ASC'),
            };
        } else {
            $qb->orderBy('e.email', 'ASC');
        }

        /** @var Employee[] $items */
        [$items, $total] = QueryBuilderPaginator::paginate($qb, $pagination);

        return ListResponse::create($items, $total, $pagination);
    }
}
