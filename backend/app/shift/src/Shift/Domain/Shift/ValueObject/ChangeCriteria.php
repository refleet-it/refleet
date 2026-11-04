<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\Shift\ValueObject;

use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use App\Shared\Domain\ValueObject\PromptSource;
use App\Shift\Shift\Domain\Shift\Exception\InvalidCriteriaException;

/**
 * Defines how the actual change is applied to a target: a free-form prompt an agent
 * carries out against the checkout, optionally preceded by organization-wide rules
 * composed from playbooks.
 */
final readonly class ChangeCriteria
{
    /**
     * @param list<PromptSource> $sources
     */
    private function __construct(
        private CriteriaModeEnum $mode,
        private ?CriteriaEngineEnum $engine,
        private string $prompt,
        private ?string $model,
        private ?string $rules,
        private array $sources,
        private bool $fixPipelines,
    ) {
    }

    /**
     * $engine selects which agent CLI runs the prompt (CLAUDE or KIRO); null defaults
     * to CLAUDE for backward compatibility with criteria created before Kiro support
     * existed. $rules is the already-composed rules text — the user sees and edits it as
     * plain text, so nothing here refers back to the playbooks it came from. $fixPipelines
     * lets an agent push fixes to the merge request when its pipeline fails because of the
     * change.
     *
     * @param PromptSource[] $sources
     *
     * @SuppressWarnings("PHPMD.BooleanArgumentFlag") A plain setting of the criteria, stored as given.
     */
    public static function ai(
        string $prompt,
        ?string $model = null,
        ?CriteriaEngineEnum $engine = null,
        ?string $rules = null,
        array $sources = [],
        bool $fixPipelines = false,
    ): self {
        if ('' === \trim($prompt)) {
            throw new InvalidCriteriaException('Prompt must not be empty for AI change criteria.');
        }

        $rules = null === $rules || '' === \trim($rules) ? null : $rules;

        return new self(CriteriaModeEnum::AI, $engine, $prompt, $model, $rules, \array_values($sources), $fixPipelines);
    }

    public function mode(): CriteriaModeEnum
    {
        return $this->mode;
    }

    public function engine(): ?CriteriaEngineEnum
    {
        return $this->engine;
    }

    public function prompt(): string
    {
        return $this->prompt;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    public function rules(): ?string
    {
        return $this->rules;
    }

    public function fixPipelines(): bool
    {
        return $this->fixPipelines;
    }

    /**
     * @return list<PromptSource>
     */
    public function sources(): array
    {
        return $this->sources;
    }
}
