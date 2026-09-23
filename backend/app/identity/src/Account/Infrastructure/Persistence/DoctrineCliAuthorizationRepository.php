<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Persistence;

use App\Identity\Account\Domain\CliAuthorization\Model\CliAuthorization;
use App\Identity\Account\Domain\CliAuthorization\Repository\CliAuthorizationRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCliAuthorizationRepository implements CliAuthorizationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(CliAuthorization $authorization): void
    {
        $this->em->persist($authorization);
        $this->em->flush();
    }

    #[\Override]
    public function delete(CliAuthorization $authorization): void
    {
        $this->em->remove($authorization);
        $this->em->flush();
    }

    #[\Override]
    public function findByUserCode(string $userCode): ?CliAuthorization
    {
        return $this->em->getRepository(CliAuthorization::class)->findOneBy(['userCode' => $userCode]);
    }

    #[\Override]
    public function findByDeviceSecretHash(string $deviceSecretHash): ?CliAuthorization
    {
        return $this->em->getRepository(CliAuthorization::class)->findOneBy(['deviceSecretHash' => $deviceSecretHash]);
    }

    #[\Override]
    public function deleteExpiredBefore(\DateTimeImmutable $moment): void
    {
        $this->em->createQueryBuilder()
            ->delete(CliAuthorization::class, 'a')
            ->where('a.expiresAt < :moment')
            ->setParameter('moment', $moment)
            ->getQuery()
            ->execute();
    }
}
