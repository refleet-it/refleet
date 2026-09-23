<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Application\Command\RequestRunnerUpdate;

use App\Runner\Runner\Application\Command\RequestRunnerUpdate\RequestRunnerUpdateCommand;
use App\Runner\Runner\Application\Command\RequestRunnerUpdate\RequestRunnerUpdateHandler;
use App\Runner\Runner\Domain\Runner\Exception\RunnerNotFoundException;
use App\Runner\Runner\Domain\Runner\Model\Runner;
use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestRunnerUpdateHandler::class)]
final class RequestRunnerUpdateHandlerTest extends TestCase
{
    private RunnerRepositoryInterface&MockObject $runners;

    private RequestRunnerUpdateHandler $handler;

    #[Test]
    public function flags_the_runner_for_its_next_heartbeat(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $runner = Runner::register(id: RunnerId::generate(), organizationId: $organizationId, name: 'runner-fleet-01');

        $this->runners
            ->expects($this->once())
            ->method('findByIdForOrganization')
            ->with($runner->id(), $organizationId)
            ->willReturn($runner);
        $this->runners->expects($this->once())->method('save')->with($runner);

        // Act
        ($this->handler)(new RequestRunnerUpdateCommand(runnerId: $runner->id()->asString(), organizationId: $organizationId->asString()));

        // Assert
        Assert::assertNotNull($runner->updateRequestedAt());
    }

    #[Test]
    public function throws_when_the_runner_does_not_exist_for_the_organization(): void
    {
        // Arrange
        $this->runners->method('findByIdForOrganization')->willReturn(null);
        $this->runners->expects($this->never())->method('save');

        // Assert
        $this->expectException(RunnerNotFoundException::class);

        // Act
        ($this->handler)(new RequestRunnerUpdateCommand(runnerId: RunnerId::generate()->asString(), organizationId: OrganizationId::generate()->asString()));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->runners = $this->createMock(RunnerRepositoryInterface::class);
        $this->handler = new RequestRunnerUpdateHandler($this->runners);
    }
}
