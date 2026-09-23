<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Domain\Playbook\Service;

use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;

/**
 * The playbooks Refleet ships with. Read-only, present in every organization, ids
 * prefixed with PlaybookDefinition::BUILT_IN_ID_PREFIX so they never collide with
 * persisted ones.
 */
interface BuiltInPlaybookCatalogInterface
{
    /**
     * @return list<PlaybookDefinition>
     */
    public function all(): array;

    public function find(string $id): ?PlaybookDefinition;
}
