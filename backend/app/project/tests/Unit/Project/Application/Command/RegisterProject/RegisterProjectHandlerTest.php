<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Project\Application\Command\RegisterProject;

use App\Project\Project\Application\Command\RegisterProject\RegisterProjectCommand;
use App\Project\Project\Application\Command\RegisterProject\RegisterProjectHandler;
use App\Project\Project\Domain\Project\Model\Project;
use App\Project\Project\Domain\Project\Repository\ProjectRepositoryInterface;
use App\Project\Project\Domain\Project\ValueObject\GitLabProjectId;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Project\Project\Domain\Project\ValueObject\ProjectId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegisterProjectHandler::class)]
final class RegisterProjectHandlerTest extends TestCase
{
    private ProjectRepositoryInterface&MockObject $projects;

    private RegisterProjectHandler $handler;

    #[Test]
    public function registers_a_new_project_when_none_exists_for_the_external_id(): void
    {
        // Arrange
        $this->projects->method('findByExternalId')->willReturn(null);

        $savedProject = null;
        $this->projects
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Project $project) use (&$savedProject): bool {
                $savedProject = $project;

                return true;
            }));

        // Act
        $result = ($this->handler)(new RegisterProjectCommand(
            organizationId: OrganizationId::generate()->asString(),
            externalId: '48210942',
            name: 'Payments Service',
            path: 'team/payments-service',
        ));

        // Assert
        Assert::assertNotNull($savedProject);
        Assert::assertTrue($result->wasCreated);
        Assert::assertSame('Payments Service', $result->name);
        Assert::assertSame('48210942', $result->externalId);
    }

    #[Test]
    public function re_registering_an_existing_external_id_updates_it_instead_of_duplicating(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $existing = Project::register(
            id: ProjectId::generate(),
            organizationId: $organizationId,
            externalId: GitLabProjectId::fromString('48210942'),
            name: 'Old Name',
            path: 'team/old-path',
            webUrl: null,
            defaultBranch: null,
            description: null,
        );
        $this->projects->method('findByExternalId')->willReturn($existing);

        $this->projects
            ->expects($this->once())
            ->method('save')
            ->with($existing);

        // Act
        $result = ($this->handler)(new RegisterProjectCommand(
            organizationId: $organizationId->asString(),
            externalId: '48210942',
            name: 'New Name',
            path: 'team/new-path',
        ));

        // Assert
        Assert::assertFalse($result->wasCreated);
        Assert::assertSame('New Name', $result->name);
        Assert::assertSame('New Name', $existing->name());
        Assert::assertSame('team/new-path', $existing->path());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->projects = $this->createMock(ProjectRepositoryInterface::class);
        $this->handler = new RegisterProjectHandler($this->projects);
    }
}
