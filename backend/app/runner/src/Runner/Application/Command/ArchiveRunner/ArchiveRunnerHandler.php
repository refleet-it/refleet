<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\ArchiveRunner;

use App\Runner\Runner\Domain\Runner\Exception\RunnerNotFoundException;
use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use App\Runner\Runner\Infrastructure\Bus\RunnerArchived\RunnerArchivedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Archiving keeps the runner and its job history, but takes it out of the live runner fleet
 * and announces it so its API key gets revoked. There is no counterpart command: archiving is
 * one-way by design.
 */
#[AsMessageHandler]
final readonly class ArchiveRunnerHandler
{
    public function __construct(
        private RunnerRepositoryInterface $runners,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ArchiveRunnerCommand $command): void
    {
        $runner = $this->runners->findByIdForOrganization(
            RunnerId::fromString($command->runnerId),
            OrganizationId::fromString($command->organizationId),
        );

        if (null === $runner) {
            throw new RunnerNotFoundException();
        }

        $runner->archive(new \DateTimeImmutable());

        $this->runners->save($runner);

        $this->bus->dispatch(new RunnerArchivedMessage(
            runnerId: $runner->id()->asString(),
            organizationId: $runner->organizationId()->asString(),
            apiKeyId: $runner->apiKeyId(),
        ));
    }
}
