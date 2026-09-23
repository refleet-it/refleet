<?php

declare(strict_types=1);

namespace App\Identity\RefreshToken\Domain\RefreshToken\Repository;

use App\Identity\RefreshToken\Domain\RefreshToken\Model\RefreshToken;

interface RefreshTokenRepositoryInterface
{
    public function findByToken(string $token): ?RefreshToken;
}
