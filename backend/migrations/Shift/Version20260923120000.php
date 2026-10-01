<?php

declare(strict_types=1);

namespace DoctrineMigrations\Shift;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Track when each open merge request was last checked against GitLab, so the poller can order its queue';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift.shift_targets ADD merge_request_checked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        // Targets already waiting on a merge request were last known to be current when
        // the runner reported them; without this they would all look never-checked and
        // the first pass would order them arbitrarily.
        $this->addSql("UPDATE shift.shift_targets SET merge_request_checked_at = merge_request_opened_at WHERE status = 'merge_request_open'");

        $this->addSql('CREATE INDEX idx_shift_targets_status_mr_checked_at ON shift.shift_targets (status, merge_request_checked_at)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX shift.idx_shift_targets_status_mr_checked_at');
        $this->addSql('ALTER TABLE shift.shift_targets DROP merge_request_checked_at');
    }
}
