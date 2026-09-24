<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Command\PollOpenMergeRequests;

use App\Shared\Domain\Service\MergeRequestStateReaderInterface;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Application\Command\PollOpenMergeRequests\PollOpenMergeRequestsCommand;
use App\Shift\Shift\Application\Command\PollOpenMergeRequests\PollOpenMergeRequestsHandler;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\Service\ShiftCompletion;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\MergeRequestStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ProjectId;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(PollOpenMergeRequestsHandler::class)]
final class PollOpenMergeRequestsHandlerTest extends TestCase
{
    private ShiftTargetRepositoryInterface&MockObject $shiftTargets;

    private ShiftRepositoryInterface&MockObject $shifts;

    private MergeRequestStateReaderInterface&MockObject $mergeRequestStates;

    private PollOpenMergeRequestsHandler $handler;

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function settles_the_targets_gitlab_reports_as_merged_or_closed(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $shiftId = ShiftId::generate();

        $merged = $this->openMergeRequest($shiftId, $organizationId, '10', '1');
        $closed = $this->openMergeRequest($shiftId, $organizationId, '10', '2');
        $stillOpen = $this->openMergeRequest($shiftId, $organizationId, '20', '3');

        $this->shiftTargets->method('findOpenMergeRequestsToCheck')->willReturn([$merged, $closed, $stillOpen]);
        $this->shiftTargets->method('countByShiftIdAndStatuses')->willReturn(1);

        $this->mergeRequestStates
            ->expects($this->once())
            ->method('statesFor')
            ->willReturnCallback(static function (string $requestedOrganizationId, array $iidsByProject) use ($organizationId): array {
                Assert::assertSame($organizationId->asString(), $requestedOrganizationId);
                Assert::assertSame(['10' => ['1', '2'], '20' => ['3']], $iidsByProject);

                return [
                    '10' => ['1' => 'merged', '2' => 'closed'],
                    '20' => ['3' => 'opened'],
                ];
            });

        $this->shiftTargets->expects($this->once())->method('saveAll');

        // Act
        ($this->handler)(new PollOpenMergeRequestsCommand());

        // Assert
        Assert::assertSame(ShiftTargetStatusEnum::COMPLETED, $merged->status());
        Assert::assertSame(MergeRequestStatusEnum::MERGED, $merged->mergeRequestStatus());
        Assert::assertSame(ShiftTargetStatusEnum::MERGE_REQUEST_CLOSED, $closed->status());
        Assert::assertSame(ShiftTargetStatusEnum::MERGE_REQUEST_OPEN, $stillOpen->status());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function completes_a_shift_once_its_last_merge_request_lands(): void
    {
        // Arrange
        $shiftId = ShiftId::generate();
        $target = $this->openMergeRequest($shiftId, OrganizationId::generate(), '10', '1');

        $this->shiftTargets->method('findOpenMergeRequestsToCheck')->willReturn([$target]);
        $this->shiftTargets->method('countByShiftIdAndStatuses')->willReturn(0);
        $this->mergeRequestStates->method('statesFor')->willReturn(['10' => ['1' => 'merged']]);

        $shift = $this->createMock(Shift::class);
        $shift->expects($this->once())->method('complete');
        $this->shifts->method('findById')->willReturn($shift);
        $this->shifts->expects($this->once())->method('save')->with($shift);

        // Act
        ($this->handler)(new PollOpenMergeRequestsCommand());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function leaves_a_target_alone_when_gitlab_says_nothing_about_it(): void
    {
        // Arrange
        $target = $this->openMergeRequest(ShiftId::generate(), OrganizationId::generate(), '10', '1');

        $this->shiftTargets->method('findOpenMergeRequestsToCheck')->willReturn([$target]);
        $this->mergeRequestStates->method('statesFor')->willReturn([]);

        $this->shifts->expects($this->never())->method('save');

        // Act
        ($this->handler)(new PollOpenMergeRequestsCommand());

        // Assert: still waiting, but marked as checked so it goes to the back of the queue
        Assert::assertSame(ShiftTargetStatusEnum::MERGE_REQUEST_OPEN, $target->status());
        Assert::assertNotNull($target->mergeRequestCheckedAt());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function marks_a_target_as_checked_even_when_the_connection_is_unreachable(): void
    {
        // Arrange
        $target = $this->openMergeRequest(ShiftId::generate(), OrganizationId::generate(), '10', '1');

        $this->shiftTargets->method('findOpenMergeRequestsToCheck')->willReturn([$target]);
        $this->mergeRequestStates->method('statesFor')->willThrowException(new \RuntimeException('organization container is down'));

        $this->shiftTargets->expects($this->once())->method('saveAll');

        // Act
        ($this->handler)(new PollOpenMergeRequestsCommand());

        // Assert
        Assert::assertSame(ShiftTargetStatusEnum::MERGE_REQUEST_OPEN, $target->status());
        Assert::assertNotNull($target->mergeRequestCheckedAt());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function falls_back_to_the_merge_request_url_when_the_iid_was_never_recorded(): void
    {
        // Arrange
        $target = $this->openMergeRequest(ShiftId::generate(), OrganizationId::generate(), '10', null, 'https://gitlab.com/acme/payments/-/merge_requests/41');

        $this->shiftTargets->method('findOpenMergeRequestsToCheck')->willReturn([$target]);
        $this->shiftTargets->method('countByShiftIdAndStatuses')->willReturn(1);

        $this->mergeRequestStates
            ->method('statesFor')
            ->willReturnCallback(static function (string $organizationId, array $iidsByProject): array {
                Assert::assertSame(['10' => ['41']], $iidsByProject);

                return ['10' => ['41' => 'merged']];
            });

        // Act
        ($this->handler)(new PollOpenMergeRequestsCommand());

        // Assert
        Assert::assertSame(ShiftTargetStatusEnum::COMPLETED, $target->status());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function asks_each_organization_about_its_own_merge_requests_only(): void
    {
        // Arrange
        $first = OrganizationId::generate();
        $second = OrganizationId::generate();

        $this->shiftTargets->method('findOpenMergeRequestsToCheck')->willReturn([
            $this->openMergeRequest(ShiftId::generate(), $first, '10', '1'),
            $this->openMergeRequest(ShiftId::generate(), $second, '20', '2'),
        ]);

        $asked = [];
        $this->mergeRequestStates
            ->expects($this->exactly(2))
            ->method('statesFor')
            ->willReturnCallback(static function (string $organizationId, array $iidsByProject) use (&$asked): array {
                $asked[$organizationId] = $iidsByProject;

                return [];
            });

        // Act
        ($this->handler)(new PollOpenMergeRequestsCommand());

        // Assert
        Assert::assertSame(['10' => ['1']], $asked[$first->asString()]);
        Assert::assertSame(['20' => ['2']], $asked[$second->asString()]);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function does_nothing_at_all_when_no_merge_request_is_due_a_check(): void
    {
        // Arrange
        $this->shiftTargets->method('findOpenMergeRequestsToCheck')->willReturn([]);
        $this->mergeRequestStates->expects($this->never())->method('statesFor');
        $this->shiftTargets->expects($this->never())->method('saveAll');

        // Act
        ($this->handler)(new PollOpenMergeRequestsCommand());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shiftTargets = $this->createMock(ShiftTargetRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->mergeRequestStates = $this->createMock(MergeRequestStateReaderInterface::class);

        $this->handler = new PollOpenMergeRequestsHandler(
            $this->shiftTargets,
            $this->mergeRequestStates,
            new ShiftCompletion($this->shifts, $this->shiftTargets),
            new NullLogger(),
        );
    }

    private function openMergeRequest(
        ShiftId $shiftId,
        OrganizationId $organizationId,
        string $projectExternalId,
        ?string $iid,
        string $mergeRequestUrl = 'https://gitlab.com/acme/payments/-/merge_requests/1',
    ): ShiftTarget {
        $target = ShiftTarget::create(
            id: ShiftTargetId::generate(),
            shiftId: $shiftId,
            organizationId: $organizationId,
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot(
                externalId: $projectExternalId,
                path: 'acme/payments',
                name: 'Payments',
                defaultBranch: 'main',
            ),
        );

        $target->startChange(ShiftTargetId::generate()->asString());
        $target->recordChangeSuccess('bumped the library', 'refleet/change', 'runner-1', $mergeRequestUrl, $iid);

        return $target;
    }
}
