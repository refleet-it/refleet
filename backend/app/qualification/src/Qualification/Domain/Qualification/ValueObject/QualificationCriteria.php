<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Domain\Qualification\ValueObject;

use App\Qualification\Qualification\Domain\Qualification\Exception\InvalidCriteriaException;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use App\Shared\Domain\ValueObject\PromptSource;

/**
 * Defines how a project is checked to see whether it qualifies: a free-form prompt an
 * agent answers with a 1–5 score against the checkout (see QualificationScore).
 */
final readonly class QualificationCriteria
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
    ) {
    }

    /**
     * $engine selects which agent CLI runs the prompt (CLAUDE or KIRO); null defaults
     * to CLAUDE for backward compatibility with criteria created before Kiro support
     * existed. $rules is the already-composed rules text — the user sees and edits it as
     * plain text, so nothing here refers back to the playbooks it came from.
     *
     * @param PromptSource[] $sources
     */
    public static function ai(
        string $prompt,
        ?string $model = null,
        ?CriteriaEngineEnum $engine = null,
        ?string $rules = null,
        array $sources = [],
    ): self {
        if ('' === \trim($prompt)) {
            throw new InvalidCriteriaException('Prompt must not be empty for AI qualification criteria.');
        }

        $rules = null === $rules || '' === \trim($rules) ? null : $rules;

        return new self(CriteriaModeEnum::AI, $engine, $prompt, $model, $rules, \array_values($sources));
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

    /**
     * @return list<PromptSource>
     */
    public function sources(): array
    {
        return $this->sources;
    }
}
