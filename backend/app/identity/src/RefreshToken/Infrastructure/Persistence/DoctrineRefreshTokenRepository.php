<?php

declare(strict_types=1);

namespace App\Identity\RefreshToken\Infrastructure\Persistence;

use App\Identity\RefreshToken\Domain\RefreshToken\Model\RefreshToken;
use App\Identity\RefreshToken\Domain\RefreshToken\Repository\RefreshTokenRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function findByToken(string $token): ?RefreshToken
    {
        return $this->em
            ->getRepository(RefreshToken::class)
            ->findOneBy(['token' => \hash('sha256', \mb_strtolower($token))]);
    }
}
