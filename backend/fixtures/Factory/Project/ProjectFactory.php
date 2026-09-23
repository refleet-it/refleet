<?php

declare(strict_types=1);

namespace App\Fixtures\Factory\Project;

use App\Project\Project\Domain\Project\Model\Project;
use App\Project\Project\Domain\Project\ValueObject\GitLabProjectId;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Project\Project\Domain\Project\ValueObject\ProjectId;
use Zenstruck\Foundry\Object\Instantiator;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

final class ProjectFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Project::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'id' => ProjectId::generate(),
            'organizationId' => OrganizationId::generate(),
            'externalId' => GitLabProjectId::fromString((string) self::faker()->unique()->numberBetween(1000, 999999)),
            'name' => self::faker()->word().' Service',
            'path' => self::faker()->slug(),
            'webUrl' => null,
            'defaultBranch' => 'main',
            'description' => null,
        ];
    }

    protected function initialize(): static
    {
        return $this->instantiateWith(Instantiator::namedConstructor('register'));
    }
}
