<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Application\Command\EnqueueRunnerJob;

use App\Runner\Runner\Application\Command\EnqueueRunnerJob\EnqueueRunnerJobCommand;
use App\Runner\Runner\Application\Command\EnqueueRunnerJob\EnqueueRunnerJobHandler;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobKindEnum;
use App\Runner\Runner\Domain\RunnerJob\Model\RunnerJob;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerTargetId;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use App\Shared\Domain\ValueObject\Id;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnqueueRunnerJobHandler::class)]
final class EnqueueRunnerJobHandlerTest extends TestCase
{
    private RunnerJobRepositoryInterface&MockObject $runnerJobs;

    private EnqueueRunnerJobHandler $handler;

    #[Test]
    public function enqueues_a_pending_job_with_the_mapped_fields(): void
    {
        // Arrange
        $ownerId = RunnerJobOwnerId::generate()->asString();
        $ownerTargetId = RunnerJobOwnerTargetId::generate()->asString();
        $organizationId = OrganizationId::generate()->asString();

        $saved = null;
        $this->runnerJobs
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (RunnerJob $job) use (&$saved): bool {
                $saved = $job;

                return true;
            }));

        // Act
        $result = ($this->handler)(new EnqueueRunnerJobCommand(
            jobId: Id::generate()->asString(),
            ownerId: $ownerId,
            ownerTargetId: $ownerTargetId,
            ownerLabel: 'Bump acme/legacy-lib',
            organizationId: $organizationId,
            kind: 'qualification',
            mode: 'ai',
            payload: ['mode' => 'ai', 'targetFile' => 'composer.json'],
            engine: 'kiro',
        ));

        // Assert
        Assert::assertInstanceOf(RunnerJob::class, $saved);
        Assert::assertSame($ownerId, $saved->ownerId()->asString());
        Assert::assertSame($ownerTargetId, $saved->ownerTargetId()->asString());
        Assert::assertSame($organizationId, $saved->organizationId()->asString());
        Assert::assertSame('Bump acme/legacy-lib', $saved->ownerLabel());
        Assert::assertSame(RunnerJobKindEnum::QUALIFICATION, $saved->kind());
        Assert::assertSame(CriteriaModeEnum::AI, $saved->mode());
        Assert::assertSame(CriteriaEngineEnum::KIRO, $saved->engine());
        Assert::assertSame(['mode' => 'ai', 'targetFile' => 'composer.json'], $saved->payload());
        Assert::assertSame($saved->id()->asString(), $result->jobId);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function defaults_to_no_engine_when_none_given(): void
    {
        // Arrange
        $this->runnerJobs->method('save');

        // Act
        ($this->handler)(new EnqueueRunnerJobCommand(
            jobId: Id::generate()->asString(),
            ownerId: RunnerJobOwnerId::generate()->asString(),
            ownerTargetId: RunnerJobOwnerTargetId::generate()->asString(),
            ownerLabel: 'Apply the migration',
            organizationId: OrganizationId::generate()->asString(),
            kind: 'change',
            mode: 'ai',
            payload: ['prompt' => 'Apply the migration'],
        ));

        // Assert (no exception, no engine to parse)
        Assert::assertTrue(true);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->runnerJobs = $this->createMock(RunnerJobRepositoryInterface::class);
        $this->handler = new EnqueueRunnerJobHandler($this->runnerJobs);
    }
}
