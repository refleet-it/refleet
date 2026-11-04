<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Command\PollMergeRequestPipelines;

use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Service\MergeRequestPipelineReaderInterface;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Application\Command\PollMergeRequestPipelines\PollMergeRequestPipelinesCommand;
use App\Shift\Shift\Application\Command\PollMergeRequestPipelines\PollMergeRequestPipelinesHandler;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\Service\ShiftJobPayloadFactory;
use App\Shift\Shift\Domain\Shift\ValueObject\AccountId;
use App\Shift\Shift\Domain\Shift\ValueObject\ChangeCriteria;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\PipelineStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ProjectId;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use App\Shift\Shift\Infrastructure\Bus\RunnerJobRequested\RunnerJobRequestedMessage;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(PollMergeRequestPipelinesHandler::class)]
final class PollMergeRequestPipelinesHandlerTest extends TestCase
{
    private const string HEAD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private ShiftTargetRepositoryInterface&MockObject $shiftTargets;

    private ShiftRepositoryInterface&MockObject $shifts;

    private MergeRequestPipelineReaderInterface&MockObject $pipelines;

    private PollMergeRequestPipelinesHandler $handler;

    /** @var list<object> */
    private array $dispatched = [];

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function queues_a_fix_for_a_failed_pipeline_with_everything_the_runner_needs(): void
    {
        // Arrange
        $target = $this->watched('10', '1');
        $this->shiftTargets->method('findPipelinesToCheck')->willReturn([$target]);
        $this->shifts->method('findById')->willReturn($this->definedShift($target));
        $this->pipelines->method('pipelinesFor')->willReturn(['10' => ['1' => $this->reported('failed')]]);
        $this->shiftTargets->expects($this->atLeastOnce())->method('save')->with($target);

        // Act
        ($this->handler)(new PollMergeRequestPipelinesCommand());

        // Assert
        Assert::assertSame(PipelineStatusEnum::FIXING, $target->pipelineStatus());
        Assert::assertCount(1, $this->dispatched);

        $job = $this->dispatched[0];
        Assert::assertInstanceOf(RunnerJobRequestedMessage::class, $job);
        Assert::assertSame('pipeline_fix', $job->kind);
        Assert::assertSame($target->pipelineFixJobId(), $job->jobId);
        Assert::assertSame($target->id()->asString(), $job->ownerTargetId);
        Assert::assertSame('kiro', $job->engine);
        Assert::assertSame([
            'branchName' => 'refleet/change-1',
            'headSha' => self::HEAD,
            'pipelineId' => '77',
            'mergeRequestIid' => '1',
        ], $job->payload['pipelineFix']);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function spends_nothing_on_a_pipeline_that_passed_or_is_still_running(): void
    {
        // Arrange
        $passed = $this->watched('10', '1');
        $running = $this->watched('10', '2');
        $this->shiftTargets->method('findPipelinesToCheck')->willReturn([$passed, $running]);
        $this->pipelines->method('pipelinesFor')->willReturn(['10' => ['1' => $this->reported('success'), '2' => $this->reported('running')]]);

        // Act
        ($this->handler)(new PollMergeRequestPipelinesCommand());

        // Assert
        Assert::assertSame(PipelineStatusEnum::PASSED, $passed->pipelineStatus());
        Assert::assertSame(PipelineStatusEnum::WAITING, $running->pipelineStatus());
        Assert::assertSame([], $this->dispatched);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function leaves_a_merge_request_that_is_no_longer_open_to_the_state_poll(): void
    {
        // Arrange
        $target = $this->watched('10', '1');
        $this->shiftTargets->method('findPipelinesToCheck')->willReturn([$target]);
        $this->pipelines->method('pipelinesFor')->willReturn(['10' => ['1' => ['state' => 'merged'] + $this->reported('failed')]]);

        // Act
        ($this->handler)(new PollMergeRequestPipelinesCommand());

        // Assert
        Assert::assertSame([], $this->dispatched);
        Assert::assertSame(PipelineStatusEnum::WAITING, $target->pipelineStatus());
        Assert::assertSame(ShiftTargetStatusEnum::MERGE_REQUEST_OPEN, $target->status());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function reads_in_batches_small_enough_for_the_internal_call_timeout(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $targets = [];
        for ($iid = 1; $iid <= 12; ++$iid) {
            $targets[] = $this->watched('10', (string) $iid, $organizationId);
        }

        $this->shiftTargets->method('findPipelinesToCheck')->willReturn($targets);

        $batches = [];
        $this->pipelines
            ->method('pipelinesFor')
            ->willReturnCallback(static function (string $organization, array $iidsByProject) use (&$batches): array {
                $batches[] = \count($iidsByProject['10']);

                return [];
            });

        // Act
        ($this->handler)(new PollMergeRequestPipelinesCommand());

        // Assert
        Assert::assertSame([5, 5, 2], $batches);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function one_unreachable_organization_does_not_stop_the_others_and_still_rotates_back(): void
    {
        // Arrange
        $broken = $this->watched('10', '1');
        $healthy = $this->watched('20', '2');
        $this->shiftTargets->method('findPipelinesToCheck')->willReturn([$broken, $healthy]);

        $this->pipelines
            ->method('pipelinesFor')
            ->willReturnCallback(function (string $organization) use ($broken): array {
                if ($organization === $broken->organizationId()->asString()) {
                    throw new \RuntimeException('organization container is down');
                }

                return ['20' => ['2' => $this->reported('success')]];
            });

        // Act
        ($this->handler)(new PollMergeRequestPipelinesCommand());

        // Assert
        Assert::assertSame(PipelineStatusEnum::WAITING, $broken->pipelineStatus());
        Assert::assertNotNull($broken->pipelineCheckedAt());
        Assert::assertSame(PipelineStatusEnum::PASSED, $healthy->pipelineStatus());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shiftTargets = $this->createMock(ShiftTargetRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->pipelines = $this->createMock(MergeRequestPipelineReaderInterface::class);
        $bus = $this->createMock(MessageBusInterface::class);
        $this->dispatched = [];
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatched[] = $message;

            return new Envelope($message);
        });

        $this->handler = new PollMergeRequestPipelinesHandler(
            $this->shiftTargets,
            $this->shifts,
            $this->pipelines,
            new ShiftJobPayloadFactory(),
            $bus,
            new NullLogger(),
        );
    }

    /**
     * @return array{state: string, headSha: string, pipelineId: ?string, pipelineSha: ?string, pipelineRef: ?string, pipelineStatus: ?string}
     */
    private function reported(string $status): array
    {
        return ['state' => 'opened', 'headSha' => self::HEAD, 'pipelineId' => '77', 'pipelineSha' => self::HEAD, 'pipelineRef' => 'refleet/change-1', 'pipelineStatus' => $status];
    }

    private function watched(string $projectExternalId, string $iid, ?OrganizationId $organizationId = null): ShiftTarget
    {
        $target = ShiftTarget::create(
            ShiftTargetId::generate(),
            ShiftId::generate(),
            $organizationId ?? OrganizationId::generate(),
            ProjectId::generate(),
            new ProjectSnapshot($projectExternalId, 'acme/payments', 'payments', 'main'),
        );
        $target->startChange('0199a000-0000-7000-8000-000000000000');
        $target->recordChangeSuccess('done', 'refleet/change-1', 'runner', 'https://gitlab.com/acme/payments/-/merge_requests/'.$iid, $iid, self::HEAD, true);

        return $target;
    }

    private function definedShift(ShiftTarget $target): Shift
    {
        $shift = Shift::draft($target->shiftId(), $target->organizationId(), 'Bump legacy-lib', null, AccountId::generate(), null);
        $shift->defineChange(ChangeCriteria::ai('Bump it', null, CriteriaEngineEnum::KIRO, null, [], true));

        return $shift;
    }
}
