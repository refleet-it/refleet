<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Persistence;

use App\Identity\Account\Domain\Account\Model\PasswordResetToken;
use App\Identity\Account\Domain\Account\Repository\PasswordResetTokenRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePasswordResetTokenRepository implements PasswordResetTokenRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(PasswordResetToken $token): void
    {
        $this->em->persist($token);
    }

    #[\Override]
    public function findByToken(string $token): ?PasswordResetToken
    {
        return $this->em
            ->getRepository(PasswordResetToken::class)
            ->findOneBy(['token' => $token]);
    }

    #[\Override]
    public function findByAccountId(string $accountId): ?PasswordResetToken
    {
        return $this->em
            ->getRepository(PasswordResetToken::class)
            ->findOneBy(['account' => $accountId]);
    }

    #[\Override]
    public function delete(PasswordResetToken $token): void
    {
        $this->em->remove($token);
    }
}
