<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Application\Query\ListPlaybooks;

use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookAppliesToEnum;
use App\Playbook\Playbook\Domain\Playbook\Service\PlaybookDirectory;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\OrganizationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListPlaybooksHandler
{
    public function __construct(
        private PlaybookDirectory $directory,
    ) {
    }

    /**
     * @return list<PlaybookView>
     */
    public function __invoke(ListPlaybooksQuery $query): array
    {
        $usage = null === $query->appliesTo ? null : PlaybookAppliesToEnum::from($query->appliesTo);

        return \array_map(
            PlaybookView::fromDefinition(...),
            $this->directory->all(OrganizationId::fromString($query->organizationId), $usage),
        );
    }
}
