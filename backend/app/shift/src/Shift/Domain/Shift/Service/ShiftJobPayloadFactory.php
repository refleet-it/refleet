<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\Shift\Service;

use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Domain\Shift\ValueObject\ChangeCriteria;

/**
 * Builds the runner job payload for one shift target. Shared by the bulk start, the
 * single-target trial run and re-runs, so every attempt runs exactly the same job.
 */
final readonly class ShiftJobPayloadFactory
{
    /**
     * @return array<string, mixed>
     */
    public function build(ChangeCriteria $criteria, ProjectSnapshot $snapshot): array
    {
        return [
            'mode' => $criteria->mode()->value,
            'prompt' => $this->buildAiChangePrompt($criteria->prompt(), $criteria->rules()),
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
     * The closing JSON object is what the runner reads (see runner/agent/src/fleet/result.ts):
     * its summary becomes the target's change summary, and the commit/merge request
     * fields feed the runner's own git and GitLab calls — the agent proposes the wording,
     * the runner performs the commit, push and merge request and falls back to the shift
     * title when a field is missing or malformed. Branching, committing, pushing and the
     * merge request itself are the runner's job in code, never the agent's, so the prompt
     * says so explicitly. The rules block, when present, is the organization's own text
     * (composed from playbooks, then edited by the user), so it goes in verbatim.
     */
    public function buildAiChangePrompt(string $prompt, ?string $rules = null): string
    {
        $prompt = \trim($prompt);
        $rulesSection = null === $rules || '' === \trim($rules) ? '' : "## Rules\n\n".\trim($rules)."\n\n";

        return <<<PROMPT
            You are applying a change to a single Git repository, already checked out in your
            current working directory. Make the change described below by editing the files in
            place. Do not create branches, commit, push, or open a merge request — the tooling
            around you does that with whatever is left modified in the working tree. If the
            repository already satisfies the request, leave it untouched and say so.

            {$rulesSection}## Change

            {$prompt}

            ## Answer format

            End your response with exactly one JSON object, on its own, as the last thing you
            write — no text after it. Its shape is:

            {"summary": "<2-3 sentences>", "commit": {"subject": "<one line, at most 72 characters>", "body": "<optional, may be empty>"}, "mergeRequest": {"title": "<one line>", "description": "<markdown>"}}

            "summary" describes what you changed and why (or why nothing needed changing). It is
            shown as-is to the person reviewing the shift: name the files touched, do not narrate
            your steps and do not ask questions — nobody can reply.

            "commit" and "mergeRequest" are the wording the tooling uses when it commits your
            changes and opens the merge request. Follow the repository's own conventions for
            them where it has any (commit format, language, scope naming), otherwise the rules
            above. If you changed nothing, still fill them in.
            PROMPT;
    }
}
