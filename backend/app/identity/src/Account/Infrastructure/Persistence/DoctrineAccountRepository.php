<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Persistence;

use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Shared\Domain\ValueObject\CursorListParameters;
use App\Shared\Domain\ValueObject\CursorListResponse;
use App\Shared\Domain\ValueObject\Id;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAccountRepository implements AccountRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(Account $account): void
    {
        $this->em->persist($account);
    }

    #[\Override]
    public function findById(Id $id): ?Account
    {
        return $this->em->find(Account::class, $id->asString());
    }

    #[\Override]
    public function findByEmail(string $email): ?Account
    {
        return $this->em
            ->getRepository(Account::class)
            ->findOneBy(['email' => \mb_strtolower($email)]);
    }

    #[\Override]
    public function update(Account $account): void
    {
        $this->em->persist($account);
        $this->em->flush();
    }

    #[\Override]
    public function getPaginatedList(CursorListParameters $parameters): CursorListResponse
    {
        $qb = $this->buildAccountQueryBuilder($parameters);

        $cursor = $parameters->getPagination()->getCursor();
        if (null !== $cursor) {
            $qb->andWhere('a.id <= :cursor')->setParameter('cursor', $cursor);
        }

        $limit = $parameters->getPagination()->getLimit();
        $qb->setMaxResults($limit + 1);

        /** @var Account[] $result */
        $result = $qb->getQuery()->getResult();
        $count = \count($result);
        $hasNextPage = $count > $limit;
        $nextCursor = null;

        if ($hasNextPage) {
            $nextCursor = $this->buildNextCursor($result, $limit);
            $result = \array_slice($result, 0, $limit);
        }

        /* @var CursorListResponse<Account> */
        return CursorListResponse::create(
            items: $result,
            pagination: $parameters->getPagination(),
            nextCursor: $nextCursor,
            hasNextPage: $hasNextPage,
        );
    }

    private function buildAccountQueryBuilder(CursorListParameters $parameters): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->em
            ->getRepository(Account::class)
            ->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', AccountStatusEnum::ACTIVE);

        foreach ($parameters->getFiltering()->getCriteria() as $filter) {
            $this->applyAccountFilter($qb, $filter->getField(), $filter->getValue());
        }

        $sorting = $parameters->getSorting();
        if (null !== $sorting) {
            match ($sorting->getField()) {
                'email' => $qb->orderBy('a.email', $sorting->getDirection()->value),
                'role' => $qb->orderBy('a.role', $sorting->getDirection()->value),
                'createdAt' => $qb->orderBy('a.createdAt', $sorting->getDirection()->value),
                'updatedAt' => $qb->orderBy('a.updatedAt', $sorting->getDirection()->value),
                default => $qb->orderBy('a.id', 'DESC'),
            };
        } else {
            $qb->orderBy('a.id', 'DESC');
        }

        return $qb;
    }

    private function applyAccountFilter(\Doctrine\ORM\QueryBuilder $qb, string $field, mixed $value): void
    {
        match ($field) {
            'email' => (static function () use ($qb, $value): void {
                \assert(\is_string($value));
                $qb->andWhere('a.email LIKE :email')->setParameter('email', '%'.$value.'%');
            })(),
            'role' => $qb->andWhere('a.role = :role')->setParameter('role', $value),
            'status' => $this->applyStatusFilter($qb, $value),
            default => null,
        };
    }

    private function applyStatusFilter(\Doctrine\ORM\QueryBuilder $qb, mixed $value): void
    {
        if ('active' === $value) {
            $qb->andWhere('a.status = :statusFilter')->setParameter('statusFilter', AccountStatusEnum::ACTIVE);
        } elseif ('inactive' === $value) {
            $qb->andWhere('a.status != :statusFilter')->setParameter('statusFilter', AccountStatusEnum::ACTIVE);
        }
    }

    /**
     * @param Account[] $result
     */
    private function buildNextCursor(array $result, int $limit): ?string
    {
        $lastItem = $result[$limit] ?? null;
        if (null === $lastItem) {
            return null;
        }

        $jsonData = \json_encode([
            'id' => $lastItem->id()->asString(),
            'timestamp' => \time(),
        ]);

        return false !== $jsonData ? \base64_encode($jsonData) : null;
    }
}
