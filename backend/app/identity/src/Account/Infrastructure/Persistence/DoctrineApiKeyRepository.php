<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Persistence;

use App\Identity\Account\Domain\ApiKey\Model\ApiKey;
use App\Identity\Account\Domain\ApiKey\Repository\ApiKeyRepositoryInterface;
use App\Identity\Account\Domain\ApiKey\ValueObject\ApiKeyId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineApiKeyRepository implements ApiKeyRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(ApiKey $apiKey): void
    {
        $this->em->persist($apiKey);
        $this->em->flush();
    }

    #[\Override]
    public function findById(ApiKeyId $id): ?ApiKey
    {
        return $this->em->find(ApiKey::class, $id->asString());
    }

    #[\Override]
    public function findByHashedSecret(string $hashedSecret): ?ApiKey
    {
        return $this->em->getRepository(ApiKey::class)->findOneBy(['hashedSecret' => $hashedSecret]);
    }

    /**
     * @return ApiKey[]
     */
    #[\Override]
    public function findAllByAccountId(string $accountId): array
    {
        /** @var ApiKey[] $result */
        $result = $this->em
            ->getRepository(ApiKey::class)
            ->createQueryBuilder('k')
            ->where('k.account = :accountId')
            ->setParameter('accountId', $accountId)
            ->orderBy('k.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
