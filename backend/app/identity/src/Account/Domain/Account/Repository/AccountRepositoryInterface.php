<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Repository;

use App\Identity\Account\Domain\Account\Model\Account;
use App\Shared\Domain\ValueObject\CursorListParameters;
use App\Shared\Domain\ValueObject\CursorListResponse;
use App\Shared\Domain\ValueObject\Id;

interface AccountRepositoryInterface
{
    public function save(Account $account): void;

    public function findById(Id $id): ?Account;

    public function findByEmail(string $email): ?Account;

    public function update(Account $account): void;

    /**
     * @return CursorListResponse<Account>
     */
    public function getPaginatedList(CursorListParameters $parameters): CursorListResponse;
}
