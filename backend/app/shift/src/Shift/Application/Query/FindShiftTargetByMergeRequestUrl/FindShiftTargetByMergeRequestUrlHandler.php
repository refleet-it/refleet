<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Query\FindShiftTargetByMergeRequestUrl;

use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Backs the GitLab merge request webhook: GitLab identifies a merge request only by
 * its URL, so this is how an incoming event gets mapped back to "our" target. Returns
 * null (rather than throwing) both when nothing matches and when a match belongs to a
 * different organization than the webhook's — the caller treats both the same way, as
 * "not ours to react to", so there's no need for the caller to tell them apart.
 */
#[AsMessageHandler]
final readonly class FindShiftTargetByMergeRequestUrlHandler
{
    public function __construct(
        private ShiftTargetRepositoryInterface $shiftTargets,
    ) {
    }

    public function __invoke(FindShiftTargetByMergeRequestUrlQuery $query): ?string
    {
        $target = $this->shiftTargets->findByMergeRequestUrl($query->mergeRequestUrl);

        if (null === $target) {
            return null;
        }

        $organizationId = OrganizationId::fromString($query->organizationId);
        if (!$target->organizationId()->equals($organizationId)) {
            return null;
        }

        return $target->id()->asString();
    }
}
