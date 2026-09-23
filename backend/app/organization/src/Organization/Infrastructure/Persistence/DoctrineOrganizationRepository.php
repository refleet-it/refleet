<?php

declare(strict_types=1);

namespace App\Organization\Organization\Infrastructure\Persistence;

use App\Organization\Organization\Domain\Organization\Model\Organization;
use App\Organization\Organization\Domain\Organization\Repository\OrganizationRepositoryInterface;
use App\Organization\Organization\Domain\Organization\ValueObject\OrganizationId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineOrganizationRepository implements OrganizationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(Organization $organization): void
    {
        $this->em->persist($organization);
        $this->em->flush();
    }

    #[\Override]
    public function findById(OrganizationId $id): ?Organization
    {
        return $this->em->find(Organization::class, $id->asString());
    }
}
