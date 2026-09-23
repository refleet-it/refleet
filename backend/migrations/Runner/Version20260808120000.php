<?php

declare(strict_types=1);

namespace DoctrineMigrations\Runner;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260808120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Remap runners.status rows stuck on the removed "online" value to "idle" — RunnerStatusEnum::ONLINE was split into WORKING/IDLE, with heartbeat now storing the idle baseline and WORKING derived at read time from an active claimed job';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE runner.runners SET status = 'idle' WHERE status = 'online'");
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        // Not meaningfully reversible: rows already idle before the up() migration ran
        // are indistinguishable from rows it just remapped from "online".
        $this->addSql("UPDATE runner.runners SET status = 'online' WHERE status = 'idle'");
    }
}
