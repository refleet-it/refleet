<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\StartCliAuthorization;

use App\Identity\Account\Domain\CliAuthorization\Model\CliAuthorization;
use App\Identity\Account\Domain\CliAuthorization\Repository\CliAuthorizationRepositoryInterface;
use App\Identity\Account\Domain\CliAuthorization\ValueObject\CliAuthorizationId;
use App\Identity\Account\Infrastructure\Security\CliAuthorizationCodeGenerator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StartCliAuthorizationHandler
{
    public const int TTL_MINUTES = 10;

    public function __construct(
        private CliAuthorizationRepositoryInterface $repository,
        private CliAuthorizationCodeGenerator $codeGenerator,
    ) {
    }

    public function __invoke(StartCliAuthorizationCommand $command): StartedCliAuthorization
    {
        $now = new \DateTimeImmutable();
        $this->repository->deleteExpiredBefore($now);

        $codes = $this->codeGenerator->generate();
        $authorization = CliAuthorization::create(
            id: CliAuthorizationId::generate(),
            userCode: $codes->userCode,
            deviceSecretHash: $codes->deviceSecretHash,
            runnerName: $command->runnerName,
            expiresAt: $now->modify(\sprintf('+%d minutes', self::TTL_MINUTES)),
        );

        $this->repository->save($authorization);

        return new StartedCliAuthorization(
            userCode: $codes->userCode,
            deviceSecret: $codes->deviceSecret,
            expiresAt: $authorization->expiresAt()->format('c'),
        );
    }
}
