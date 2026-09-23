<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Application\Query\ComposePrompt;

final readonly class ComposePromptQuery
{
    /**
     * @param list<string>          $ruleIds    in the order the rules should appear
     * @param array<string, string> $parameters values for the task's `{{name}}` placeholders
     */
    public function __construct(
        public string $organizationId,
        public string $appliesTo,
        public array $ruleIds,
        public ?string $taskId,
        public array $parameters,
    ) {
    }
}
