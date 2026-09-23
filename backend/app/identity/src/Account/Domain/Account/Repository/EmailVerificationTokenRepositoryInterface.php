<?php

declare(strict_types=1);

namespace App\Identity\Account\Domain\Account\Repository;

use App\Identity\Account\Domain\Account\Model\EmailVerificationToken;

interface EmailVerificationTokenRepositoryInterface
{
    public function save(EmailVerificationToken $token): void;

    public function findByToken(string $token): ?EmailVerificationToken;
}
