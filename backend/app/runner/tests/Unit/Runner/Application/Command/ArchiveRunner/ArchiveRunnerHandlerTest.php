<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Application\Command\ArchiveRunner;

use App\Runner\Runner\Application\Command\ArchiveRunner\ArchiveRunnerCommand;
use App\Runner\Runner\Application\Command\ArchiveRunner\ArchiveRunnerHandler;
use App\Runner\Runner\Domain\Runner\Exception\RunnerNotFoundException;
use App\Runner\Runner\Domain\Runner\Model\Runner;
use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use App\Runner\Runner\Infrastructure\Bus\RunnerArchived\RunnerArchivedMessage;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(ArchiveRunnerHandler::class)]
final class ArchiveRunnerHandlerTest extends TestCase
{
    private RunnerRepositoryInterface&MockObject $runners;

    private MessageBusInterface&MockObject $bus;

    private ArchiveRunnerHandler $handler;

    #[Test]
    public function archives_the_runner_and_announces_it_so_its_key_can_be_revoked(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01',
            apiKeyId: 'key-1',
        );

        $this->runners->method('findByIdForOrganization')->willReturn($runner);
        $this->runners->expects($this->once())->method('save')->with($runner);

        $published = null;
        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$published): Envelope {
                $published = $message;

                return new Envelope($message);
            });

        // Act
        ($this->handler)(new ArchiveRunnerCommand(
            runnerId: $runner->id()->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertTrue($runner->isArchived());
        Assert::assertInstanceOf(RunnerArchivedMessage::class, $published);
        Assert::assertSame($runner->id()->asString(), $published->runnerId);
        Assert::assertSame('key-1', $published->apiKeyId);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function announces_archiving_even_when_no_key_was_ever_recorded(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01',
        );

        $this->runners->method('findByIdForOrganization')->willReturn($runner);

        $published = null;
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$published): Envelope {
                $published = $message;

                return new Envelope($message);
            });

        // Act
        ($this->handler)(new ArchiveRunnerCommand(
            runnerId: $runner->id()->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertInstanceOf(RunnerArchivedMessage::class, $published);
        Assert::assertNull($published->apiKeyId);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function refuses_to_archive_a_runner_from_another_organization(): void
    {
        // Arrange
        $this->runners->method('findByIdForOrganization')->willReturn(null);
        $this->bus->expects($this->never())->method('dispatch');

        $this->expectException(RunnerNotFoundException::class);

        // Act
        ($this->handler)(new ArchiveRunnerCommand(
            runnerId: RunnerId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->runners = $this->createMock(RunnerRepositoryInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->handler = new ArchiveRunnerHandler($this->runners, $this->bus);
    }
}
