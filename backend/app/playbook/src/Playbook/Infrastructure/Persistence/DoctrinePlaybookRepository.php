<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Infrastructure\Persistence;

use App\Playbook\Playbook\Domain\Playbook\Model\Playbook;
use App\Playbook\Playbook\Domain\Playbook\Repository\PlaybookRepositoryInterface;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\OrganizationId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePlaybookRepository implements PlaybookRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(Playbook $playbook): void
    {
        $this->em->persist($playbook);
        $this->em->flush();
    }

    #[\Override]
    public function remove(Playbook $playbook): void
    {
        $this->em->remove($playbook);
        $this->em->flush();
    }

    #[\Override]
    public function findByIdForOrganization(PlaybookId $id, OrganizationId $organizationId): ?Playbook
    {
        /** @var Playbook|null $playbook */
        $playbook = $this->em
            ->getRepository(Playbook::class)
            ->findOneBy(['id' => $id->asString(), 'organizationId' => $organizationId->asString()]);

        return $playbook;
    }

    #[\Override]
    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        return $this->em
            ->getRepository(Playbook::class)
            ->findBy(['organizationId' => $organizationId->asString()], ['name' => 'ASC', 'id' => 'ASC']);
    }
}
