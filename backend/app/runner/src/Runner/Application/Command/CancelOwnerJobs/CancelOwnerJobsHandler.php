<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\CancelOwnerJobs;

use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CancelOwnerJobsHandler
{
    public function __construct(
        private RunnerJobRepositoryInterface $runnerJobs,
    ) {
    }

    public function __invoke(CancelOwnerJobsCommand $command): void
    {
        $jobs = $this->runnerJobs->findNonTerminalByOwnerId(
            RunnerJobOwnerId::fromString($command->ownerId),
            OrganizationId::fromString($command->organizationId),
        );

        foreach ($jobs as $job) {
            $job->cancel();
        }

        $this->runnerJobs->saveAll($jobs);
    }
}
