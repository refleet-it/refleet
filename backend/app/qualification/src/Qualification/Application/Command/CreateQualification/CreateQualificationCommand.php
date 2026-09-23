<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Command\CreateQualification;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class CreateQualificationCommand implements CommandInterface
{
    /**
     * @param string[]|null                                                       $projectIds           explicit target project ids; null/empty resolves to the whole organization fleet
     * @param list<array{id: string, name: string, kind: string, builtIn?: bool}> $qualificationSources
     */
    public function __construct(
        public string $organizationId,
        public string $title,
        public ?string $description,
        public string $createdByAccountId,
        public string $qualificationMode,
        public ?string $qualificationEngine = null,
        public ?string $qualificationPrompt = null,
        public ?string $qualificationModel = null,
        public ?array $projectIds = null,
        public ?string $qualificationRules = null,
        public array $qualificationSources = [],
    ) {
    }
}
