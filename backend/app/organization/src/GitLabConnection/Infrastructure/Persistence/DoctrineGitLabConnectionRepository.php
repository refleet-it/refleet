<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Infrastructure\Persistence;

use App\Organization\GitLabConnection\Domain\Connection\Exception\GitLabConnectionNotFoundException;
use App\Organization\GitLabConnection\Domain\Connection\Model\GitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineGitLabConnectionRepository implements GitLabConnectionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(GitLabConnection $connection): void
    {
        $this->em->persist($connection);
        $this->em->flush();
    }

    #[\Override]
    public function remove(GitLabConnection $connection): void
    {
        $this->em->remove($connection);
        $this->em->flush();
    }

    #[\Override]
    public function findByOrganizationId(OrganizationId $organizationId): ?GitLabConnection
    {
        return $this->em
            ->getRepository(GitLabConnection::class)
            ->findOneBy(['organizationId' => $organizationId->asString()]);
    }

    #[\Override]
    public function withExclusiveLock(OrganizationId $organizationId, \Closure $work): mixed
    {
        return $this->em->wrapInTransaction(function () use ($organizationId, $work): mixed {
            $connection = $this->findByOrganizationId($organizationId);

            if (null === $connection) {
                throw new GitLabConnectionNotFoundException();
            }

            $this->em->lock($connection, LockMode::PESSIMISTIC_WRITE);
            // lock() only takes the row lock; the entity may hold data loaded before another
            // transaction committed its refresh, so re-read it now that we own the row.
            $this->em->refresh($connection);

            return $work($connection);
        });
    }
}
