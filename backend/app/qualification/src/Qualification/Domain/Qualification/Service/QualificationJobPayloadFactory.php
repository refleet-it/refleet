<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Domain\Qualification\Service;

use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationScore;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\ValueObject\ProjectSnapshot;

/**
 * Builds the runner job payload for one qualification target. Shared by the initial
 * start and by retries, so a retried target runs exactly the same job as the first attempt.
 */
final readonly class QualificationJobPayloadFactory
{
    /**
     * @return array<string, mixed>
     */
    public function build(QualificationCriteria $criteria, ProjectSnapshot $snapshot): array
    {
        return [
            'mode' => $criteria->mode()->value,
            'prompt' => $this->buildAiQualificationPrompt($criteria->prompt(), $criteria->rules()),
            'model' => $criteria->model(),
            'engine' => ($criteria->engine() ?? CriteriaEngineEnum::CLAUDE)->value,
            'project' => [
                'externalId' => $snapshot->externalId(),
                'path' => $snapshot->path(),
                'name' => $snapshot->name(),
                'defaultBranch' => $snapshot->defaultBranch(),
            ],
        ];
    }

    /**
     * Wraps the user's free-form criteria in the instructions a headless agent needs to
     * produce a usable verdict:
     *
     * - it must inspect the checkout rather than answer from the project name, and must
     *   not edit anything (qualification jobs are read-only);
     * - the answer is one JSON object the runner parses (see runner/agent/src/fleet/result.ts)
     *   — the score is mandatory, nobody can answer a clarifying question, and a response
     *   without a valid score is reported as a failed run, not a "no";
     * - the scale is spelled out so every agent grades against the same bar; where the
     *   cut-off falls is QualificationScore's business, not the agent's;
     * - the reasoning is capped at 2-3 sentences because it is stored as the target's
     *   summary and shown verbatim on the qualification page;
     * - the rules block, when present, is the organization's own text (composed from
     *   playbooks, then edited by the user), so it goes in verbatim.
     */
    public function buildAiQualificationPrompt(string $criteria, ?string $rules = null): string
    {
        $criteria = \trim($criteria);
        $rulesSection = null === $rules || '' === \trim($rules) ? '' : "## Rules\n\n".\trim($rules)."\n\n";
        $min = QualificationScore::MIN;
        $max = QualificationScore::MAX;

        return <<<PROMPT
            You are qualifying a single Git repository against the criteria below. The repository
            is already checked out in your current working directory. Investigate the actual code
            first: locate and read the files that can settle the question (dependency manifests,
            lock files, configuration, source) using the read-only tools available to you. Base the
            verdict only on what you find in this checkout — never on the project's name, path or
            assumptions about what it probably contains. Do not create, modify or delete any files.

            {$rulesSection}## Criteria

            {$criteria}

            ## Answer format

            End your response with exactly one JSON object, on its own, as the last thing you
            write — no text after it. Its shape is:

            {"score": <integer {$min}-{$max}>, "reasoning": "<2-3 sentences>"}

            "score" grades how well the repository matches the criteria:

            - 5 — clearly matches; the evidence is explicit and unambiguous.
            - 4 — matches; the evidence is solid, with at most a minor uncertainty.
            - 3 — unclear; the evidence is mixed or too thin to decide either way.
            - 2 — mostly does not match; only weak or indirect hints in favour.
            - 1 — clearly does not match.

            If the evidence is inconclusive, score 3 or lower and say in the reasoning what was
            missing. "reasoning" is shown as-is to the person reviewing the qualification: name
            the concrete files (or lines) that decided it, do not narrate your steps and do not
            ask questions — nobody can reply. The JSON object is mandatory: a response without a
            valid score is recorded as a failed run, not as a verdict.
            PROMPT;
    }
}
