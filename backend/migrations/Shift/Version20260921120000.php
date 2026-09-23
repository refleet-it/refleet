<?php

declare(strict_types=1);

namespace DoctrineMigrations\Shift;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Drop the static (regex) change mode: fold existing static criteria into an AI prompt so their history stays readable and drop the target_file/pattern/replacement columns';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE shift.shifts
            SET change_prompt = 'In the file "' || COALESCE(change_target_file, '') || '", replace every match of the regular expression ' || COALESCE(change_pattern, '') || ' with: ' || COALESCE(change_replacement, '')
            WHERE change_mode = 'static'
            SQL);
        $this->addSql("UPDATE shift.shifts SET change_mode = 'ai', change_engine = 'claude' WHERE change_mode = 'static'");
        $this->addSql('ALTER TABLE shift.shifts DROP change_target_file');
        $this->addSql('ALTER TABLE shift.shifts DROP change_pattern');
        $this->addSql('ALTER TABLE shift.shifts DROP change_replacement');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift.shifts ADD change_replacement TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE shift.shifts ADD change_pattern TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE shift.shifts ADD change_target_file VARCHAR(255) DEFAULT NULL');
    }
}
