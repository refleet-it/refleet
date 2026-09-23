<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Command;

use App\Identity\Account\Application\Command\Register\RegisterCommand;
use App\Identity\Account\Domain\Account\Enum\RoleEnum;
use App\Identity\Account\Domain\Account\Exception\EmailAlreadyUsedException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The way into a fresh instance. Registration through the API waits on a verification email,
 * which a self-hosted instance cannot send until someone is already inside to configure the
 * mailer — so the first account is made here instead, already verified.
 *
 * It creates an account and nothing else: the organisation is created on first sign-in by the
 * onboarding the application already has, and everyone after the first is invited from there.
 */
#[AsCommand(
    name: 'refleet:create-account',
    description: 'Create a verified account, for bootstrapping an instance',
)]
final class CreateAccountCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email address to sign in with')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password; prompted for, hidden, when omitted')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant instance administrator rights');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $this->resolveEmail($input, $io);
        $password = $this->resolvePassword($input, $io);

        if ('' === $email || '' === $password) {
            $io->error('An email address and a password are both required.');

            return Command::INVALID;
        }

        $role = true === $input->getOption('admin') ? RoleEnum::ADMINISTRATOR : RoleEnum::USER;

        try {
            $this->commandBus->dispatch(new RegisterCommand(
                email: $email,
                plainPassword: $password,
                role: $role,
                termsAccepted: true,
                marketingConsent: false,
                skipEmailVerification: true,
            ));
        } catch (\Throwable $throwable) {
            $previous = $throwable->getPrevious() ?? $throwable;

            $io->error($previous instanceof EmailAlreadyUsedException
                ? \sprintf('An account already exists for %s.', $email)
                : $previous->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Created %s (%s). Sign in to create your organisation.', $email, $role->value));

        return Command::SUCCESS;
    }

    private function resolveEmail(InputInterface $input, SymfonyStyle $io): string
    {
        $email = $input->getOption('email');

        if (\is_string($email) && '' !== $email) {
            return $email;
        }

        $answer = $io->ask('Email address');

        return \is_string($answer) ? $answer : '';
    }

    private function resolvePassword(InputInterface $input, SymfonyStyle $io): string
    {
        $password = $input->getOption('password');

        if (\is_string($password) && '' !== $password) {
            return $password;
        }

        // Hidden, and never echoed back — passing it as an option leaves it in shell history.
        $question = new Question('Password');
        $question->setHidden(true);

        $answer = $io->askQuestion($question);

        return \is_string($answer) ? $answer : '';
    }
}
