<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use Doctrine\ORM\QueryBuilder;

final class QueryBuilderPaginator
{
    /**
     * Applies offset pagination to a query builder and reports the total row count
     * (counted before LIMIT/OFFSET, on a clone so the caller's builder is untouched).
     *
     * @return array{0: list<mixed>, 1: int}
     */
    public static function paginate(QueryBuilder $qb, PaginationParameters $pagination): array
    {
        $rootAliases = $qb->getRootAliases();
        $alias = $rootAliases[0] ?? throw new \LogicException('QueryBuilder must have at least one root alias.');

        $total = (int) (clone $qb)
            ->resetDQLPart('orderBy')
            ->select(\sprintf('COUNT(%s)', $alias))
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<mixed> $items */
        $items = (clone $qb)
            ->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->getLimit())
            ->getQuery()
            ->getResult();

        return [$items, $total];
    }
}
