<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Project\Domain\Project\Model;

use App\Project\Project\Domain\Project\Event\ProjectRegistered;
use App\Project\Project\Domain\Project\Model\Project;
use App\Project\Project\Domain\Project\ValueObject\GitLabProjectId;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Project\Project\Domain\Project\ValueObject\ProjectId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Project::class)]
final class ProjectTest extends TestCase
{
    #[Test]
    public function registers_a_project(): void
    {
        $id = ProjectId::generate();
        $organizationId = OrganizationId::generate();
        $externalId = GitLabProjectId::fromString('48210942');

        $project = Project::register(
            id: $id,
            organizationId: $organizationId,
            externalId: $externalId,
            name: 'Payments Service',
            path: 'backend-team/payments-service',
            webUrl: 'https://gitlab.com/backend-team/payments-service',
            defaultBranch: 'main',
            description: 'Handles payment processing',
        );

        Assert::assertTrue($project->id()->equals($id));
        Assert::assertTrue($project->organizationId()->equals($organizationId));
        Assert::assertTrue($project->externalId()->equals($externalId));
        Assert::assertSame('Payments Service', $project->name());
        Assert::assertSame('backend-team/payments-service', $project->path());
        Assert::assertSame('main', $project->defaultBranch());
        Assert::assertNotNull($project->lastSyncedAt());
    }

    #[Test]
    public function records_project_registered_event(): void
    {
        $id = ProjectId::generate();

        $project = Project::register(
            id: $id,
            organizationId: OrganizationId::generate(),
            externalId: GitLabProjectId::fromString('1'),
            name: 'Payments Service',
            path: 'team/payments-service',
            webUrl: null,
            defaultBranch: null,
            description: null,
        );

        $events = $project->getRecordedDomainEvents();

        Assert::assertCount(1, $events);
        Assert::assertInstanceOf(ProjectRegistered::class, $events[0]);
        Assert::assertTrue($events[0]->projectId->equals($id));
        Assert::assertSame('Payments Service', $events[0]->name);
    }

    #[Test]
    public function update_overwrites_mutable_fields_and_refreshes_last_synced_at(): void
    {
        $project = Project::register(
            id: ProjectId::generate(),
            organizationId: OrganizationId::generate(),
            externalId: GitLabProjectId::fromString('1'),
            name: 'Old Name',
            path: 'team/old-path',
            webUrl: null,
            defaultBranch: null,
            description: null,
        );
        $firstSyncedAt = $project->lastSyncedAt();

        $project->update(
            name: 'New Name',
            path: 'team/new-path',
            webUrl: 'https://gitlab.com/team/new-path',
            defaultBranch: 'develop',
            description: 'Updated description',
        );

        Assert::assertSame('New Name', $project->name());
        Assert::assertSame('team/new-path', $project->path());
        Assert::assertSame('https://gitlab.com/team/new-path', $project->webUrl());
        Assert::assertSame('develop', $project->defaultBranch());
        Assert::assertSame('Updated description', $project->description());
        Assert::assertNotNull($project->lastSyncedAt());
        Assert::assertGreaterThanOrEqual($firstSyncedAt, $project->lastSyncedAt());
    }

    #[Test]
    public function archives_a_project(): void
    {
        $project = Project::register(
            id: ProjectId::generate(),
            organizationId: OrganizationId::generate(),
            externalId: GitLabProjectId::fromString('1'),
            name: 'Payments Service',
            path: 'team/payments-service',
            webUrl: null,
            defaultBranch: null,
            description: null,
        );

        Assert::assertFalse($project->isArchived());

        $project->archive();

        Assert::assertTrue($project->isArchived());
        Assert::assertNotNull($project->archivedAt());
    }

    #[Test]
    public function update_clears_archived_at_because_the_project_was_seen_alive_again(): void
    {
        $project = Project::register(
            id: ProjectId::generate(),
            organizationId: OrganizationId::generate(),
            externalId: GitLabProjectId::fromString('1'),
            name: 'Payments Service',
            path: 'team/payments-service',
            webUrl: null,
            defaultBranch: null,
            description: null,
        );
        $project->archive();

        $project->update(
            name: 'Payments Service',
            path: 'team/payments-service',
            webUrl: null,
            defaultBranch: null,
            description: null,
        );

        Assert::assertFalse($project->isArchived());
        Assert::assertNull($project->archivedAt());
    }
}
