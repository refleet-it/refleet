<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

/**
 * Reads merge request states straight from an organization's GitLab, so a context that
 * tracks open merge requests can check on them without owning the connection or its
 * access token.
 *
 * Deliberately total: an organization with no connection, a token GitLab rejects or an
 * unreachable instance all come back as missing entries rather than an exception. The
 * caller polls many organizations in one pass and one broken connection must not stop
 * the others; the owning context logs what it could not read.
 */
interface MergeRequestStateReaderInterface
{
    /**
     * @param array<string, list<string>> $iidsByProjectExternalId GitLab project id => merge request iids
     *
     * @return array<string, array<string, string>> GitLab project id => iid => state, one of
     *                                              "opened", "closed", "locked" or "merged".
     *                                              Anything GitLab did not answer for is absent.
     */
    public function statesFor(string $organizationId, array $iidsByProjectExternalId): array;
}
