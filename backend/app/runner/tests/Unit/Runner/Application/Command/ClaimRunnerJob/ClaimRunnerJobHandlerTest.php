<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Application\Command\ClaimRunnerJob;

use App\Runner\Runner\Application\Command\ClaimRunnerJob\ClaimRunnerJobCommand;
use App\Runner\Runner\Application\Command\ClaimRunnerJob\ClaimRunnerJobHandler;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobKindEnum;
use App\Runner\Runner\Domain\RunnerJob\Model\RunnerJob;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerTargetId;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClaimRunnerJobHandler::class)]
final class ClaimRunnerJobHandlerTest extends TestCase
{
    private RunnerJobRepositoryInterface&MockObject $runnerJobs;

    private ClaimRunnerJobHandler $handler;

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function returns_null_when_no_job_is_available(): void
    {
        // Arrange
        $this->runnerJobs->method('claimNext')->willReturn(null);

        // Act
        $result = ($this->handler)(new ClaimRunnerJobCommand(
            runnerId: 'runner-fleet-01',
            organizationId: OrganizationId::generate()->asString(),
        ));

        // Assert
        Assert::assertNull($result);
    }

    #[Test]
    public function maps_a_claimed_job_to_the_output_dto(): void
    {
        // Arrange
        $job = $this->createStub(RunnerJob::class);
        $job->method('id')->willReturn(RunnerJobId::generate());
        $job->method('ownerId')->willReturn(RunnerJobOwnerId::generate());
        $job->method('ownerTargetId')->willReturn(RunnerJobOwnerTargetId::generate());
        $job->method('kind')->willReturn(RunnerJobKindEnum::QUALIFICATION);
        $job->method('mode')->willReturn(CriteriaModeEnum::AI);
        $job->method('payload')->willReturn(['mode' => 'ai']);
        $job->method('leaseExpiresAt')->willReturn(new \DateTimeImmutable('2026-01-01T00:05:00+00:00'));

        $this->runnerJobs
            ->expects($this->once())
            ->method('claimNext')
            ->willReturn($job);

        // Act
        $result = ($this->handler)(new ClaimRunnerJobCommand(
            runnerId: 'runner-fleet-01',
            organizationId: OrganizationId::generate()->asString(),
            supportedKinds: ['qualification'],
            supportedModes: ['ai'],
        ));

        // Assert
        Assert::assertNotNull($result);
        Assert::assertSame('qualification', $result->kind);
        Assert::assertSame('ai', $result->mode);
        Assert::assertSame(['mode' => 'ai'], $result->payload);
    }

    #[Test]
    public function converts_supported_engines_to_enums_before_claiming(): void
    {
        // Arrange
        $job = $this->createStub(RunnerJob::class);
        $job->method('id')->willReturn(RunnerJobId::generate());
        $job->method('ownerId')->willReturn(RunnerJobOwnerId::generate());
        $job->method('ownerTargetId')->willReturn(RunnerJobOwnerTargetId::generate());
        $job->method('kind')->willReturn(RunnerJobKindEnum::CHANGE);
        $job->method('mode')->willReturn(CriteriaModeEnum::AI);
        $job->method('payload')->willReturn(['mode' => 'ai', 'engine' => 'kiro']);
        $job->method('leaseExpiresAt')->willReturn(new \DateTimeImmutable('2026-01-01T00:05:00+00:00'));

        $this->runnerJobs
            ->expects($this->once())
            ->method('claimNext')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                [CriteriaEngineEnum::KIRO],
                $this->anything(),
                $this->anything(),
            )
            ->willReturn($job);

        // Act
        $result = ($this->handler)(new ClaimRunnerJobCommand(
            runnerId: 'runner-fleet-01',
            organizationId: OrganizationId::generate()->asString(),
            supportedModes: ['ai'],
            supportedEngines: ['kiro'],
        ));

        // Assert
        Assert::assertNotNull($result);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->runnerJobs = $this->createMock(RunnerJobRepositoryInterface::class);
        $this->handler = new ClaimRunnerJobHandler($this->runnerJobs);
    }
}
