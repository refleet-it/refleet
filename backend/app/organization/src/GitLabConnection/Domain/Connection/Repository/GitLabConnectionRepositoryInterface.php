<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Domain\Connection\Repository;

use App\Organization\GitLabConnection\Domain\Connection\Model\GitLabConnection;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;

interface GitLabConnectionRepositoryInterface
{
    public function save(GitLabConnection $connection): void;

    public function remove(GitLabConnection $connection): void;

    public function findByOrganizationId(OrganizationId $organizationId): ?GitLabConnection;

    /**
     * Runs $work with the organization's connection row locked for update, inside one
     * transaction, so concurrent callers serialize on it. Throws when there is no connection.
     *
     * @template T
     *
     * @param \Closure(GitLabConnection): T $work
     *
     * @return T
     */
    public function withExclusiveLock(OrganizationId $organizationId, \Closure $work): mixed;
}
