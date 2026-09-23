<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Service;

use App\Identity\Account\Application\Command\Register\RegisterCommand;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\AccountNotFoundException;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Shared\Domain\Service\InvitedAccountRegistrarInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class InvitedAccountRegistrar implements InvitedAccountRegistrarInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private AccountRepositoryInterface $accounts,
    ) {
    }

    #[\Override]
    public function registerFromInvitation(string $email, string $plainPassword): string
    {
        try {
            $this->bus->dispatch(new RegisterCommand(
                email: $email,
                plainPassword: $plainPassword,
                role: RoleEnum::USER,
                termsAccepted: true,
                marketingConsent: false,
                skipEmailVerification: true,
            ));
        } catch (HandlerFailedException $handlerFailedException) {
            throw $handlerFailedException->getPrevious() ?? $handlerFailedException;
        }

        $account = $this->accounts->findByEmail($email);

        if (null === $account) {
            throw new AccountNotFoundException();
        }

        return $account->id()->asString();
    }
}
