<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Domain\Playbook\Service;

use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookAppliesToEnum;
use App\Playbook\Playbook\Domain\Playbook\Model\Playbook;
use App\Playbook\Playbook\Domain\Playbook\Repository\PlaybookRepositoryInterface;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\OrganizationId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookId;
use Ramsey\Uuid\Uuid;

/**
 * One view over the built-in catalog and the organization's own playbooks, keyed by the
 * ids the API exposes: `builtin:<file>` for shipped ones, the UUID for persisted ones.
 */
final readonly class PlaybookDirectory
{
    public function __construct(
        private PlaybookRepositoryInterface $playbooks,
        private BuiltInPlaybookCatalogInterface $builtIns,
    ) {
    }

    /**
     * Built-ins first, then the organization's own — both in their natural order.
     *
     * @return list<PlaybookDefinition>
     */
    public function all(OrganizationId $organizationId, ?PlaybookAppliesToEnum $usage = null): array
    {
        $own = \array_map(static fn (Playbook $playbook): PlaybookDefinition => $playbook->definition(), $this->playbooks->findByOrganizationId($organizationId));
        $definitions = [...$this->builtIns->all(), ...\array_values($own)];

        if (null === $usage) {
            return $definitions;
        }

        return \array_values(\array_filter($definitions, static fn (PlaybookDefinition $definition): bool => $definition->appliesTo()->covers($usage)));
    }

    public function find(string $id, OrganizationId $organizationId): ?PlaybookDefinition
    {
        if (\str_starts_with($id, PlaybookDefinition::BUILT_IN_ID_PREFIX)) {
            return $this->builtIns->find($id);
        }

        if (!Uuid::isValid($id)) {
            return null;
        }

        return $this->playbooks->findByIdForOrganization(PlaybookId::fromString($id), $organizationId)?->definition();
    }
}
