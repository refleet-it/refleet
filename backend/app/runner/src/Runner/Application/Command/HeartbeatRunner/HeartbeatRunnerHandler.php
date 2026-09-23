<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\HeartbeatRunner;

use App\Runner\Runner\Domain\Runner\Model\Runner;
use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\Service\LatestRunnerVersionProviderInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Registers the runner on its first heartbeat (find-or-create by organization + free-text
 * name), otherwise just marks the existing row idle and refreshes lastSeenAt.
 *
 * The lookup skips archived runners, so reusing an archived runner's name registers a
 * separate, new runner rather than reviving the archived one.
 *
 * The answer also tells the runner where it stands release-wise — the newest published
 * version, whether it is behind, and whether someone asked for an update from the
 * dashboard — so the runner can stop between jobs and let its supervisor restart it.
 */
#[AsMessageHandler]
final readonly class HeartbeatRunnerHandler
{
    public function __construct(
        private RunnerRepositoryInterface $runners,
        private LatestRunnerVersionProviderInterface $latestVersions,
    ) {
    }

    public function __invoke(HeartbeatRunnerCommand $command): HeartbeatedRunner
    {
        $organizationId = OrganizationId::fromString($command->organizationId);
        $now = new \DateTimeImmutable();

        $runner = $this->runners->findByOrganizationIdAndName($organizationId, $command->name);

        if (null === $runner) {
            $runner = Runner::register(
                id: RunnerId::generate(),
                organizationId: $organizationId,
                name: $command->name,
                lastSeenAt: $now,
                apiKeyId: $command->apiKeyId,
                supportedEngines: $command->supportedEngines,
                supportedModels: $command->supportedModels,
            );
        }

        $runner->heartbeat($now, $command->apiKeyId, $command->supportedEngines, $command->supportedModels, $command->usage, $command->version);
        // A browser session heartbeating carries no version and would otherwise swallow a
        // request meant for the runner process.
        $updateRequested = null !== $command->version && $runner->consumeUpdateRequest();

        $this->runners->save($runner);

        $latestVersion = $this->latestVersions->latestVersion();

        return new HeartbeatedRunner(
            id: $runner->id()->asString(),
            name: $runner->name(),
            status: $runner->status()->value,
            lastSeenAt: $now->format('c'),
            supportedEngines: $runner->supportedEngines(),
            supportedModels: $runner->supportedModels(),
            usage: $runner->usage(),
            version: $runner->version(),
            latestVersion: $latestVersion,
            updateAvailable: $runner->isBehind($latestVersion),
            updateRequested: $updateRequested,
        );
    }
}
