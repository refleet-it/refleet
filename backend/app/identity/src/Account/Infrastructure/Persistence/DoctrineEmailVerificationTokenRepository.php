<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Persistence;

use App\Identity\Account\Domain\Account\Model\EmailVerificationToken;
use App\Identity\Account\Domain\Account\Repository\EmailVerificationTokenRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineEmailVerificationTokenRepository implements EmailVerificationTokenRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(EmailVerificationToken $token): void
    {
        $this->em->persist($token);
    }

    #[\Override]
    public function findByToken(string $token): ?EmailVerificationToken
    {
        return $this->em
            ->getRepository(EmailVerificationToken::class)
            ->findOneBy(['token' => $token]);
    }
}
