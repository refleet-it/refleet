<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\ClaimRunnerJob;

use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobKindEnum;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ClaimRunnerJobHandler
{
    private const int DEFAULT_LEASE_SECONDS = 300;

    public function __construct(
        private RunnerJobRepositoryInterface $runnerJobs,
    ) {
    }

    public function __invoke(ClaimRunnerJobCommand $command): ?ClaimedRunnerJob
    {
        $organizationId = OrganizationId::fromString($command->organizationId);

        $kinds = null !== $command->supportedKinds
            ? \array_map(RunnerJobKindEnum::from(...), $command->supportedKinds)
            : RunnerJobKindEnum::cases();

        $modes = null !== $command->supportedModes
            ? \array_map(CriteriaModeEnum::from(...), $command->supportedModes)
            : null;

        $engines = null !== $command->supportedEngines
            ? \array_map(CriteriaEngineEnum::from(...), $command->supportedEngines)
            : null;

        $job = $this->runnerJobs->claimNext(
            organizationId: $organizationId,
            kinds: $kinds,
            modes: $modes,
            engines: $engines,
            runnerId: $command->runnerId,
            leaseSeconds: self::DEFAULT_LEASE_SECONDS,
        );

        if (null === $job) {
            return null;
        }

        return new ClaimedRunnerJob(
            jobId: $job->id()->asString(),
            ownerId: $job->ownerId()->asString(),
            ownerTargetId: $job->ownerTargetId()->asString(),
            ownerLabel: $job->ownerLabel(),
            kind: $job->kind()->value,
            mode: $job->mode()->value,
            payload: $job->payload(),
            leaseExpiresAt: $job->leaseExpiresAt()?->format('c') ?? '',
        );
    }
}
