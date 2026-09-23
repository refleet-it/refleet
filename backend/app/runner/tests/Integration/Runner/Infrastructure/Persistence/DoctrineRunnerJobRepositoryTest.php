<?php

declare(strict_types=1);

namespace App\Tests\Integration\Runner\Runner\Infrastructure\Persistence;

use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobKindEnum;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobStatusEnum;
use App\Runner\Runner\Domain\RunnerJob\Model\RunnerJob;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerTargetId;
use App\Runner\Runner\Infrastructure\Persistence\DoctrineRunnerJobRepository;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[CoversClass(DoctrineRunnerJobRepository::class)]
final class DoctrineRunnerJobRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private DoctrineRunnerJobRepository $repository;

    private EntityManagerInterface $entityManager;

    #[Test]
    public function saves_and_finds_a_runner_job_by_id(): void
    {
        // Arrange
        $id = RunnerJobId::generate();
        $job = $this->createJob($id, OrganizationId::generate(), RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);

        // Act
        $this->repository->save($job);
        $this->entityManager->clear();
        $found = $this->repository->findById($id);

        // Assert
        Assert::assertInstanceOf(RunnerJob::class, $found);
        Assert::assertTrue($found->id()->equals($id));
    }

    #[Test]
    public function claim_next_claims_the_oldest_pending_job_for_the_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $older = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $this->repository->save($older);

        \usleep(1_000);

        $newer = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $this->repository->save($newer);

        // Act
        $claimed = $this->repository->claimNext($organizationId, [RunnerJobKindEnum::QUALIFICATION], null, null, 'runner-1', 300);

        // Assert
        Assert::assertInstanceOf(RunnerJob::class, $claimed);
        Assert::assertTrue($claimed->id()->equals($older->id()));
        Assert::assertSame(RunnerJobStatusEnum::CLAIMED, $claimed->status());
    }

    #[Test]
    public function claim_next_returns_null_when_no_job_matches(): void
    {
        // Act
        $claimed = $this->repository->claimNext(OrganizationId::generate(), [RunnerJobKindEnum::QUALIFICATION], null, null, 'runner-1', 300);

        // Assert
        Assert::assertNull($claimed);
    }

    #[Test]
    public function claim_next_does_not_claim_jobs_belonging_to_another_organization(): void
    {
        // Arrange
        $job = $this->createJob(RunnerJobId::generate(), OrganizationId::generate(), RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $this->repository->save($job);

        // Act
        $claimed = $this->repository->claimNext(OrganizationId::generate(), [RunnerJobKindEnum::QUALIFICATION], null, null, 'runner-1', 300);

        // Assert
        Assert::assertNull($claimed);
    }

    #[Test]
    public function claim_next_filters_by_kind(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $changeJob = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::CHANGE, CriteriaModeEnum::AI);
        $this->repository->save($changeJob);

        // Act
        $claimed = $this->repository->claimNext($organizationId, [RunnerJobKindEnum::QUALIFICATION], null, null, 'runner-1', 300);

        // Assert
        Assert::assertNull($claimed);
    }

    #[Test]
    public function claim_next_filters_by_supported_modes_when_given(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $aiJob = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $this->repository->save($aiJob);

        // Act: runner advertises no mode this job runs in
        $claimed = $this->repository->claimNext($organizationId, [RunnerJobKindEnum::QUALIFICATION], [], null, 'runner-1', 300);

        // Assert
        Assert::assertNull($claimed);

        // Act: runner supports ai jobs
        $claimedAi = $this->repository->claimNext($organizationId, [RunnerJobKindEnum::QUALIFICATION], [CriteriaModeEnum::AI], null, 'runner-1', 300);

        // Assert
        Assert::assertInstanceOf(RunnerJob::class, $claimedAi);
        Assert::assertTrue($claimedAi->id()->equals($aiJob->id()));
    }

    #[Test]
    public function claim_next_filters_by_supported_engines_when_given(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $claudeJob = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::CHANGE, CriteriaModeEnum::AI, CriteriaEngineEnum::CLAUDE);
        $this->repository->save($claudeJob);

        // Act: runner only supports the Kiro engine
        $claimed = $this->repository->claimNext($organizationId, [RunnerJobKindEnum::CHANGE], [CriteriaModeEnum::AI], [CriteriaEngineEnum::KIRO], 'runner-1', 300);

        // Assert
        Assert::assertNull($claimed);

        // Act: runner supports the Claude engine
        $claimedClaude = $this->repository->claimNext($organizationId, [RunnerJobKindEnum::CHANGE], [CriteriaModeEnum::AI], [CriteriaEngineEnum::CLAUDE], 'runner-1', 300);

        // Assert
        Assert::assertInstanceOf(RunnerJob::class, $claimedClaude);
        Assert::assertTrue($claimedClaude->id()->equals($claudeJob->id()));
    }

    #[Test]
    public function claim_next_does_not_let_an_engine_filter_block_a_job_with_no_engine(): void
    {
        // Arrange: a static/regex job carries no distinct engine value worth filtering —
        // an engine filter scoped to a different engine must not accidentally exclude it.
        $organizationId = OrganizationId::generate();
        $job = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::CHANGE, CriteriaModeEnum::AI);
        $this->repository->save($job);

        // Act
        $claimed = $this->repository->claimNext($organizationId, [RunnerJobKindEnum::CHANGE], null, [CriteriaEngineEnum::CLAUDE], 'runner-1', 300);

        // Assert
        Assert::assertInstanceOf(RunnerJob::class, $claimed);
        Assert::assertTrue($claimed->id()->equals($job->id()));
    }

    #[Test]
    public function claim_next_does_not_reclaim_a_job_whose_lease_has_not_expired_yet(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $job = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $this->repository->save($job);

        $firstClaim = $this->repository->claimNext($organizationId, [RunnerJobKindEnum::QUALIFICATION], null, null, 'runner-1', 300);
        Assert::assertInstanceOf(RunnerJob::class, $firstClaim);

        // Act: another runner tries to claim immediately, lease still valid
        $secondClaim = $this->repository->claimNext($organizationId, [RunnerJobKindEnum::QUALIFICATION], null, null, 'runner-2', 300);

        // Assert
        Assert::assertNull($secondClaim);
    }

    #[Test]
    public function claim_next_reclaims_a_job_whose_lease_has_expired(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $job = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $this->repository->save($job);

        $firstClaim = $this->repository->claimNext($organizationId, [RunnerJobKindEnum::QUALIFICATION], null, null, 'runner-1', 300);
        Assert::assertInstanceOf(RunnerJob::class, $firstClaim);

        // Simulate an expired lease (runner crashed without reporting)
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE runner.runner_jobs SET lease_expires_at = :expiredAt WHERE id = :id',
            [
                'expiredAt' => (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s'),
                'id' => $job->id()->asString(),
            ],
        );

        // Act: a second runner reclaims the abandoned job
        $secondClaim = $this->repository->claimNext($organizationId, [RunnerJobKindEnum::QUALIFICATION], null, null, 'runner-2', 300);

        // Assert
        Assert::assertInstanceOf(RunnerJob::class, $secondClaim);
        Assert::assertTrue($secondClaim->id()->equals($job->id()));
        Assert::assertSame(2, $secondClaim->attemptCount());
    }

    #[Test]
    public function finds_jobs_claimed_by_a_runner_name_ordered_by_created_at_desc(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $older = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $older->claim('runner-fleet-01', 300);

        $this->repository->save($older);

        \usleep(1_000);

        $newer = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::CHANGE, CriteriaModeEnum::AI);
        $newer->claim('runner-fleet-01', 300);

        $this->repository->save($newer);

        $unclaimed = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $this->repository->save($unclaimed);

        $this->entityManager->clear();

        // Act
        $result = $this->repository->getPaginatedListByClaimedBy($organizationId, 'runner-fleet-01', PaginationParameters::fromRequest());

        // Assert
        Assert::assertCount(2, $result->getItems());
        Assert::assertTrue($result->getItems()[0]->id()->equals($newer->id()));
        Assert::assertTrue($result->getItems()[1]->id()->equals($older->id()));
    }

    #[Test]
    public function does_not_include_jobs_claimed_by_another_runner_or_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $ownJob = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $ownJob->claim('runner-fleet-01', 300);

        $this->repository->save($ownJob);

        $otherRunnerJob = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $otherRunnerJob->claim('runner-fleet-02', 300);

        $this->repository->save($otherRunnerJob);

        $otherOrgJob = $this->createJob(RunnerJobId::generate(), OrganizationId::generate(), RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $otherOrgJob->claim('runner-fleet-01', 300);

        $this->repository->save($otherOrgJob);

        $this->entityManager->clear();

        // Act
        $result = $this->repository->getPaginatedListByClaimedBy($organizationId, 'runner-fleet-01', PaginationParameters::fromRequest());

        // Assert
        Assert::assertCount(1, $result->getItems());
        Assert::assertTrue($result->getItems()[0]->id()->equals($ownJob->id()));
    }

    #[Test]
    public function finds_non_terminal_jobs_for_an_owner_including_both_pending_and_claimed(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $ownerId = RunnerJobOwnerId::generate();

        $pending = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI, ownerId: $ownerId);
        $this->repository->save($pending);

        $claimed = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI, ownerId: $ownerId);
        $claimed->claim('runner-1', 300);

        $this->repository->save($claimed);

        $succeeded = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI, ownerId: $ownerId);
        $succeeded->claim('runner-1', 300);
        $succeeded->reportSuccess('done');

        $this->repository->save($succeeded);

        $otherOwnerJob = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $this->repository->save($otherOwnerJob);

        $this->entityManager->clear();

        // Act
        $jobs = $this->repository->findNonTerminalByOwnerId($ownerId, $organizationId);

        // Assert
        Assert::assertCount(2, $jobs);
        $ids = \array_map(static fn (RunnerJob $job): string => $job->id()->asString(), $jobs);
        Assert::assertContains($pending->id()->asString(), $ids);
        Assert::assertContains($claimed->id()->asString(), $ids);
        Assert::assertNotContains($succeeded->id()->asString(), $ids);
        Assert::assertNotContains($otherOwnerJob->id()->asString(), $ids);
    }

    #[Test]
    public function finds_no_non_terminal_jobs_when_the_owner_has_none_in_flight(): void
    {
        // Act
        $jobs = $this->repository->findNonTerminalByOwnerId(RunnerJobOwnerId::generate(), OrganizationId::generate());

        // Assert
        Assert::assertSame([], $jobs);
    }

    #[Test]
    public function finds_names_with_an_active_claimed_job(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $working = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $working->claim('runner-fleet-01', 300);

        $this->repository->save($working);

        $idle = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $this->repository->save($idle);

        $this->entityManager->clear();

        // Act
        $names = $this->repository->findNamesWithActiveClaimedJob($organizationId, ['runner-fleet-01', 'runner-fleet-02']);

        // Assert
        Assert::assertSame(['runner-fleet-01'], $names);
    }

    #[Test]
    public function does_not_count_a_claimed_job_whose_lease_has_expired_as_active(): void
    {
        // Arrange: an expired lease means the original runner is presumed dead/stalled
        // for that job — claimNext() already treats it as reclaimable, so it must not
        // keep reporting the runner as WORKING either.
        $organizationId = OrganizationId::generate();

        $job = $this->createJob(RunnerJobId::generate(), $organizationId, RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $job->claim('runner-fleet-01', 300);

        $this->repository->save($job);

        $this->entityManager->getConnection()->executeStatement(
            'UPDATE runner.runner_jobs SET lease_expires_at = :expiredAt WHERE id = :id',
            [
                'expiredAt' => (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s'),
                'id' => $job->id()->asString(),
            ],
        );

        $this->entityManager->clear();

        // Act
        $names = $this->repository->findNamesWithActiveClaimedJob($organizationId, ['runner-fleet-01']);

        // Assert
        Assert::assertSame([], $names);
    }

    #[Test]
    public function does_not_include_names_from_another_organization(): void
    {
        // Arrange
        $job = $this->createJob(RunnerJobId::generate(), OrganizationId::generate(), RunnerJobKindEnum::QUALIFICATION, CriteriaModeEnum::AI);
        $job->claim('runner-fleet-01', 300);

        $this->repository->save($job);

        $this->entityManager->clear();

        // Act
        $names = $this->repository->findNamesWithActiveClaimedJob(OrganizationId::generate(), ['runner-fleet-01']);

        // Assert
        Assert::assertSame([], $names);
    }

    #[Test]
    public function returns_an_empty_array_when_given_no_names(): void
    {
        // Act
        $names = $this->repository->findNamesWithActiveClaimedJob(OrganizationId::generate(), []);

        // Assert
        Assert::assertSame([], $names);
    }

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->repository = $container->get(DoctrineRunnerJobRepository::class);

        $managerRegistry = $container->get(ManagerRegistry::class);
        $entityManager = $managerRegistry->getManagerForClass(RunnerJob::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
    }

    private function createJob(
        RunnerJobId $id,
        OrganizationId $organizationId,
        RunnerJobKindEnum $kind,
        CriteriaModeEnum $mode,
        ?CriteriaEngineEnum $engine = null,
        ?RunnerJobOwnerId $ownerId = null,
    ): RunnerJob {
        return RunnerJob::enqueue(
            id: $id,
            ownerId: $ownerId ?? RunnerJobOwnerId::generate(),
            ownerTargetId: RunnerJobOwnerTargetId::generate(),
            organizationId: $organizationId,
            kind: $kind,
            ownerLabel: 'Bump acme/legacy-lib',
            mode: $mode,
            payload: ['mode' => $mode->value, 'targetFile' => 'composer.json', 'pattern' => '"acme/legacy-lib"'],
            engine: $engine,
        );
    }
}
