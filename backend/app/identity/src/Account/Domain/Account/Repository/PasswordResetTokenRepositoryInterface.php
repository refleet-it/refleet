<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Repository;

use App\Identity\Account\Domain\Account\Model\PasswordResetToken;

interface PasswordResetTokenRepositoryInterface
{
    public function save(PasswordResetToken $token): void;

    public function findByToken(string $token): ?PasswordResetToken;

    public function findByAccountId(string $accountId): ?PasswordResetToken;

    public function delete(PasswordResetToken $token): void;
}
