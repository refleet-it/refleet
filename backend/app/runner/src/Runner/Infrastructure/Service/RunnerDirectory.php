<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Service;

use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Shared\Domain\Service\RunnerDirectoryInterface;

final readonly class RunnerDirectory implements RunnerDirectoryInterface
{
    public function __construct(
        private RunnerRepositoryInterface $runners,
    ) {
    }

    #[\Override]
    public function idsByNameForOrganization(string $organizationId): array
    {
        $map = [];
        foreach ($this->runners->findByOrganizationId(OrganizationId::fromString($organizationId)) as $runner) {
            $map[$runner->name()] = $runner->id()->asString();
        }

        return $map;
    }
}
