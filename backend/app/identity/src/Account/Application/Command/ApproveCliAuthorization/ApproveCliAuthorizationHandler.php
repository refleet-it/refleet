<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\ApproveCliAuthorization;

use App\Identity\Account\Domain\Account\Exception\AccountNotFoundException;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\CliAuthorization\Exception\CliAuthorizationNotFoundException;
use App\Identity\Account\Domain\CliAuthorization\Repository\CliAuthorizationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ApproveCliAuthorizationHandler
{
    public function __construct(
        private CliAuthorizationRepositoryInterface $authorizations,
        private AccountRepositoryInterface $accounts,
    ) {
    }

    public function __invoke(ApproveCliAuthorizationCommand $command): void
    {
        $authorization = $this->authorizations->findByUserCode($command->userCode);
        if (null === $authorization) {
            throw new CliAuthorizationNotFoundException();
        }

        $account = $this->accounts->findById(AccountId::fromString($command->accountId));
        if (null === $account) {
            throw new AccountNotFoundException();
        }

        $authorization->approve($account);

        $this->authorizations->save($authorization);
    }
}
