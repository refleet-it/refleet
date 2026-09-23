<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Domain\Playbook\ValueObject;

use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\ValueObject\PromptSource;

/**
 * The text playbooks produced, ready to be pasted into a Shift's or Qualification's
 * editable fields. Nothing here is stored by this context.
 */
final readonly class ComposedPrompt
{
    /**
     * @param list<PromptSource> $sources
     */
    public function __construct(
        private ?string $rules,
        private ?string $prompt,
        private ?CriteriaEngineEnum $engine,
        private ?string $model,
        private array $sources,
    ) {
    }

    public function rules(): ?string
    {
        return $this->rules;
    }

    public function prompt(): ?string
    {
        return $this->prompt;
    }

    public function engine(): ?CriteriaEngineEnum
    {
        return $this->engine;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    /**
     * @return list<PromptSource>
     */
    public function sources(): array
    {
        return $this->sources;
    }
}
