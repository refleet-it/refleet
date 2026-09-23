<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\RequestRunnerUpdate;

use App\Runner\Runner\Domain\Runner\Exception\RunnerNotFoundException;
use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Flags the runner so its next heartbeat tells it to stop after the jobs in progress; what
 * runs afterwards is up to whatever supervises the process (see docs/runner/installation.md).
 */
#[AsMessageHandler]
final readonly class RequestRunnerUpdateHandler
{
    public function __construct(
        private RunnerRepositoryInterface $runners,
    ) {
    }

    public function __invoke(RequestRunnerUpdateCommand $command): void
    {
        $runner = $this->runners->findByIdForOrganization(
            RunnerId::fromString($command->runnerId),
            OrganizationId::fromString($command->organizationId),
        );

        if (null === $runner) {
            throw new RunnerNotFoundException();
        }

        $runner->requestUpdate(new \DateTimeImmutable());

        $this->runners->save($runner);
    }
}
