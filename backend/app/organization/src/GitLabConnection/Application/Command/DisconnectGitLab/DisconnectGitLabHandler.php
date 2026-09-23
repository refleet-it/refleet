<?php

declare(strict_types=1);

namespace App\Organization\GitLabConnection\Application\Command\DisconnectGitLab;

use App\Organization\GitLabConnection\Domain\Connection\Exception\GitLabConnectionNotFoundException;
use App\Organization\GitLabConnection\Domain\Connection\Repository\GitLabConnectionRepositoryInterface;
use App\Organization\GitLabConnection\Domain\Connection\ValueObject\OrganizationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DisconnectGitLabHandler
{
    public function __construct(
        private GitLabConnectionRepositoryInterface $connections,
    ) {
    }

    public function __invoke(DisconnectGitLabCommand $command): void
    {
        $connection = $this->connections->findByOrganizationId(OrganizationId::fromString($command->organizationId));

        if (null === $connection) {
            throw new GitLabConnectionNotFoundException();
        }

        $this->connections->remove($connection);
    }
}
