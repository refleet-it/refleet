<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Query\GetCliAuthorization;

use App\Identity\Account\Domain\CliAuthorization\Exception\CliAuthorizationExpiredException;
use App\Identity\Account\Domain\CliAuthorization\Exception\CliAuthorizationNotFoundException;
use App\Identity\Account\Domain\CliAuthorization\Repository\CliAuthorizationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCliAuthorizationHandler
{
    public function __construct(
        private CliAuthorizationRepositoryInterface $repository,
    ) {
    }

    public function __invoke(GetCliAuthorizationQuery $query): CliAuthorizationReadModel
    {
        $authorization = $this->repository->findByUserCode($query->userCode);
        if (null === $authorization) {
            throw new CliAuthorizationNotFoundException();
        }

        if ($authorization->isExpired()) {
            throw new CliAuthorizationExpiredException();
        }

        return new CliAuthorizationReadModel(
            runnerName: $authorization->runnerName(),
            status: $authorization->status()->value,
            createdAt: $authorization->createdAt()->format('c'),
            expiresAt: $authorization->expiresAt()->format('c'),
        );
    }
}
