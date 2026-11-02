<?php

declare(strict_types=1);

namespace DoctrineMigrations\Identity;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102130749 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Store the identifier an OAuth provider gives an account, so identity stops resting on the email address alone';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE identity.account_oauth_identities (id UUID NOT NULL, provider VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_account_oauth_identities_provider_subject ON identity.account_oauth_identities (provider, subject)');
        $this->addSql('CREATE INDEX IDX_DCC3A3879B6B5FBA ON identity.account_oauth_identities (account_id)');
        $this->addSql('ALTER TABLE identity.account_oauth_identities ADD CONSTRAINT FK_DCC3A3879B6B5FBA FOREIGN KEY (account_id) REFERENCES identity.accounts (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        // Nothing is backfilled on the way up, so dropping the table loses only the links made
        // since. The accounts themselves, and `accounts.oauth_providers`, are untouched either
        // way: this table records which identity a provider reported, not that one exists.
        $this->addSql('ALTER TABLE identity.account_oauth_identities DROP CONSTRAINT FK_DCC3A3879B6B5FBA');
        $this->addSql('DROP TABLE identity.account_oauth_identities');
    }
}
