<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\DenyCliAuthorization;

use App\Identity\Account\Domain\CliAuthorization\Exception\CliAuthorizationNotFoundException;
use App\Identity\Account\Domain\CliAuthorization\Repository\CliAuthorizationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DenyCliAuthorizationHandler
{
    public function __construct(
        private CliAuthorizationRepositoryInterface $repository,
    ) {
    }

    public function __invoke(DenyCliAuthorizationCommand $command): void
    {
        $authorization = $this->repository->findByUserCode($command->userCode);
        if (null === $authorization) {
            throw new CliAuthorizationNotFoundException();
        }

        $authorization->deny();

        $this->repository->save($authorization);
    }
}
