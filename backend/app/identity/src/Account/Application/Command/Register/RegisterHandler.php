<?php

declare(strict_types=1);

namespace App\Identity\Account\Application\Command\Register;

use App\Identity\Account\Domain\Account\Enum\AccountStatusEnum;
use App\Identity\Account\Domain\Account\Exception\EmailAlreadyUsedException;
use App\Identity\Account\Domain\Account\Model\Account;
use App\Identity\Account\Domain\Account\Repository\AccountRepositoryInterface;
use App\Identity\Account\Domain\Account\ValueObject\AccountId;
use App\Identity\Account\Domain\Account\ValueObject\Email;
use App\Identity\Account\Domain\Account\ValueObject\HashedPassword;
use App\Identity\Account\Infrastructure\Service\PasswordHasher;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegisterHandler
{
    public function __construct(
        private AccountRepositoryInterface $repository,
        private PasswordHasher $hasher,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RegisterCommand $command): void
    {
        $this->logger->info('Started registering account', ['email' => $command->email]);
        $id = AccountId::generate();
        $hash = $this->hasher->hash($command->plainPassword);

        $accountByEmail = $this->repository->findByEmail($command->email);

        if (null !== $accountByEmail) {
            $this->logger->info('Registration failed: email already exists', ['email' => $command->email]);

            throw new EmailAlreadyUsedException($command->email);
        }

        $status = $command->skipEmailVerification
            ? AccountStatusEnum::ACTIVE
            : AccountStatusEnum::PENDING_EMAIL_VERIFICATION;

        $account = Account::create(
            id: $id,
            email: Email::fromString($command->email),
            hashedPassword: HashedPassword::fromString($hash),
            role: $command->role,
            status: $status,
            marketingConsent: $command->marketingConsent,
        );

        if (!$command->skipEmailVerification) {
            $account->requestEmailVerification();
        }

        try {
            $this->repository->save($account);
        } catch (UniqueConstraintViolationException) {
            $this->logger->info('Registration failed: email already exists (race condition caught)', ['email' => $command->email]);

            throw new EmailAlreadyUsedException($command->email);
        }

        $this->logger->info('Account registered successfully', [
            'accountId' => $id->asString(),
            'email' => $command->email,
            'emailVerificationSkipped' => $command->skipEmailVerification,
        ]);
    }
}
