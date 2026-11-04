<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

/**
 * Reads the pipeline at the head of a merge request straight from an organization's
 * GitLab, so a context watching one can do so without owning the connection or its token.
 *
 * Total in the same way as MergeRequestStateReaderInterface: anything that could not be
 * read comes back as a missing entry rather than an exception. Unlike that port this costs
 * one GitLab call per merge request, because the list endpoint does not carry
 * `head_pipeline`, so callers keep each batch small.
 */
interface MergeRequestPipelineReaderInterface
{
    /**
     * @param array<string, list<string>> $iidsByProjectExternalId GitLab project id => merge request iids
     *
     * @return array<string, array<string, array{state: string, headSha: string, pipelineId: ?string, pipelineSha: ?string, pipelineRef: ?string, pipelineStatus: ?string}>>
     *                                                                                                                                                                       GitLab project id => iid => what GitLab reports; the pipeline fields are null while the merge request has none
     */
    public function pipelinesFor(string $organizationId, array $iidsByProjectExternalId): array;
}
