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
     * The job for a pipeline that failed on the merge request. The runner fetches the failed
     * jobs' logs itself and adds them to this prompt, so they never pass through or get
     * stored in Refleet.
     *
     * @param array{branchName: ?string, headSha: string, pipelineId: ?string, mergeRequestIid: ?string} $pipelineFix
     *
     * @return array<string, mixed>
     */
    public function buildPipelineFix(ChangeCriteria $criteria, ProjectSnapshot $snapshot, array $pipelineFix): array
    {
        return [
            ...$this->build($criteria, $snapshot),
            'prompt' => $this->buildPipelineFixPrompt($criteria->prompt(), $criteria->rules(), $snapshot->defaultBranch() ?? 'main'),
            'pipelineFix' => $pipelineFix,
        ];
    }

    /**
     * The verdict decides whether anything is pushed, so the prompt has to make "not my
     * fault" a respectable answer: an agent that only knows how to fix things will rewrite
     * unrelated code to turn someone else's outage green. The same goes for silencing the
     * failure, which is the cheapest way to a passing pipeline and worth nothing.
     */
    public function buildPipelineFixPrompt(string $prompt, ?string $rules, string $targetBranch): string
    {
        $prompt = \trim($prompt);
        $rulesSection = null === $rules || '' === \trim($rules) ? '' : "## Rules\n\n".\trim($rules)."\n\n";

        return <<<PROMPT
            A change you made to this Git repository was opened as a merge request and its CI
            pipeline failed. The merge request's branch is checked out in your current working
            directory; the change itself is `git diff origin/{$targetBranch}...HEAD`.

            First decide whether that change caused the failure. Compare the failed jobs' logs
            below with the diff, and weigh the status of the latest pipeline on {$targetBranch}.
            It did not cause it if the same job fails without it, or if the failure comes from
            infrastructure, an outage, a flaky test or code the change does not touch or affect.
            Saying so is a good outcome: leave the working tree untouched and explain why.

            If it did cause the failure, fix the code by editing files in place, staying within
            what the change set out to do. Never make the pipeline pass by disabling, skipping or
            weakening tests, by loosening lint or type rules, or by editing the CI configuration,
            unless the change was itself about those. If that is the only way you can see, leave
            the working tree untouched and say so. Do not create branches, commit, push, or open
            a merge request — the tooling around you does that with whatever is left modified.

            {$rulesSection}## The original change

            {$prompt}

            ## Answer format

            End your response with exactly one JSON object, on its own, as the last thing you
            write — no text after it. Its shape is:

            {"causedByChange": <true or false>, "summary": "<2-3 sentences>", "commit": {"subject": "<one line, at most 72 characters>", "body": "<optional, may be empty>"}}

            "summary" says what failed and why, and what you did about it. It is posted on the
            merge request as-is: do not narrate your steps and do not ask questions — nobody can
            reply. "commit" is the wording used for your fix, following the repository's own
            conventions where it has any. Leave it out if you changed nothing.
            PROMPT;
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
