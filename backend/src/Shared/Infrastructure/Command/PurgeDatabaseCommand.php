<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Command;

use Doctrine\Common\DataFixtures\Purger\PurgerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-database',
    description: 'Purge all data from all entity managers',
)]
final class PurgeDatabaseCommand extends Command
{
    public function __construct(
        private readonly PurgerInterface $purger,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Purging database...');

        try {
            $this->purger->purge();
            $io->success('Database purged successfully!');

            return Command::SUCCESS;
        } catch (\Throwable $throwable) {
            $io->error('Failed to purge database: '.$throwable->getMessage());

            return Command::FAILURE;
        }
    }
}
