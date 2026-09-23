<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Application\Query\GetAvailableModels;

use App\Runner\Runner\Application\Query\GetAvailableModels\GetAvailableModelsHandler;
use App\Runner\Runner\Application\Query\GetAvailableModels\GetAvailableModelsQuery;
use App\Runner\Runner\Domain\Runner\Model\Runner;
use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetAvailableModelsHandler::class)]
final class GetAvailableModelsHandlerTest extends TestCase
{
    private RunnerRepositoryInterface&Stub $runners;

    private GetAvailableModelsHandler $handler;

    #[Test]
    public function unions_supported_models_across_runners_grouped_by_engine(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $claudeRunner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01-claude',
            supportedEngines: ['claude'],
            supportedModels: ['claude-sonnet-5', 'claude-opus-5'],
        );
        $kiroRunner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01-kiro',
            supportedEngines: ['kiro'],
            supportedModels: ['kiro-default'],
        );
        // A second claude runner reporting an overlapping model plus a new one — the
        // result must be deduped, not just concatenated.
        $secondClaudeRunner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-02-claude',
            supportedEngines: ['claude'],
            supportedModels: ['claude-sonnet-5', 'claude-haiku-4-5-20251001'],
        );

        $this->runners->method('findByOrganizationId')->willReturn([$claudeRunner, $kiroRunner, $secondClaudeRunner]);

        // Act
        $result = ($this->handler)(new GetAvailableModelsQuery(organizationId: $organizationId->asString()));

        // Assert
        Assert::assertSame(['claude-sonnet-5', 'claude-opus-5', 'claude-haiku-4-5-20251001'], $result->claude);
        Assert::assertSame(['kiro-default'], $result->kiro);
    }

    #[Test]
    public function excludes_archived_runners(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $archivedRunner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-retired-claude',
            supportedEngines: ['claude'],
            supportedModels: ['claude-sonnet-5'],
        );
        $archivedRunner->archive(new \DateTimeImmutable());

        $this->runners->method('findByOrganizationId')->willReturn([$archivedRunner]);

        // Act
        $result = ($this->handler)(new GetAvailableModelsQuery(organizationId: $organizationId->asString()));

        // Assert
        Assert::assertSame([], $result->claude);
        Assert::assertSame([], $result->kiro);
    }

    #[Test]
    public function a_runner_that_never_reported_supported_models_contributes_nothing(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01-claude',
            supportedEngines: ['claude'],
        );

        $this->runners->method('findByOrganizationId')->willReturn([$runner]);

        // Act
        $result = ($this->handler)(new GetAvailableModelsQuery(organizationId: $organizationId->asString()));

        // Assert
        Assert::assertSame([], $result->claude);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->runners = $this->createStub(RunnerRepositoryInterface::class);
        $this->handler = new GetAvailableModelsHandler($this->runners);
    }
}
