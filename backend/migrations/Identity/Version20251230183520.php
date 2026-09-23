<?php

declare(strict_types=1);

namespace DoctrineMigrations\Identity;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251230183520 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create the identity schema (accounts, tokens, api keys)';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS identity');
        $this->addSql('CREATE TABLE identity.accounts (id UUID NOT NULL, email VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, version INT DEFAULT 1 NOT NULL, terms_accepted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, marketing_consent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, oauth_providers JSON NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6EFB15F3E7927C74 ON identity.accounts (email)');
        $this->addSql('CREATE TABLE identity.email_verification_tokens (id UUID NOT NULL, token VARCHAR(255) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_used BOOLEAN NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C8CE78305F37A13B ON identity.email_verification_tokens (token)');
        $this->addSql('CREATE INDEX IDX_C8CE78309B6B5FBA ON identity.email_verification_tokens (account_id)');
        $this->addSql('CREATE TABLE identity.password_reset_tokens (id UUID NOT NULL, token VARCHAR(255) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_used BOOLEAN NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_17F30C0A5F37A13B ON identity.password_reset_tokens (token)');
        $this->addSql('CREATE INDEX IDX_17F30C0A9B6B5FBA ON identity.password_reset_tokens (account_id)');
        $this->addSql('CREATE TABLE identity.refresh_tokens (id UUID NOT NULL, token VARCHAR(255) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_revoked BOOLEAN NOT NULL, account_id UUID NOT NULL, impersonator_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9CAD7EB35F37A13B ON identity.refresh_tokens (token)');
        $this->addSql('CREATE INDEX IDX_9CAD7EB39B6B5FBA ON identity.refresh_tokens (account_id)');
        $this->addSql('CREATE TABLE identity.api_keys (id UUID NOT NULL, account_id UUID NOT NULL, name VARCHAR(100) NOT NULL, key_prefix VARCHAR(16) NOT NULL, hashed_secret VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_api_keys_hashed_secret ON identity.api_keys (hashed_secret)');
        $this->addSql('CREATE INDEX idx_api_keys_account_id ON identity.api_keys (account_id)');
        $this->addSql('ALTER TABLE identity.email_verification_tokens ADD CONSTRAINT FK_C8CE78309B6B5FBA FOREIGN KEY (account_id) REFERENCES identity.accounts (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE identity.password_reset_tokens ADD CONSTRAINT FK_17F30C0A9B6B5FBA FOREIGN KEY (account_id) REFERENCES identity.accounts (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE identity.refresh_tokens ADD CONSTRAINT FK_9CAD7EB39B6B5FBA FOREIGN KEY (account_id) REFERENCES identity.accounts (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE identity.api_keys ADD CONSTRAINT fk_api_keys_account_id FOREIGN KEY (account_id) REFERENCES identity.accounts (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity.email_verification_tokens DROP CONSTRAINT FK_C8CE78309B6B5FBA');
        $this->addSql('ALTER TABLE identity.password_reset_tokens DROP CONSTRAINT FK_17F30C0A9B6B5FBA');
        $this->addSql('ALTER TABLE identity.refresh_tokens DROP CONSTRAINT FK_9CAD7EB39B6B5FBA');
        $this->addSql('ALTER TABLE identity.api_keys DROP CONSTRAINT fk_api_keys_account_id');
        $this->addSql('DROP TABLE identity.accounts');
        $this->addSql('DROP TABLE identity.email_verification_tokens');
        $this->addSql('DROP TABLE identity.password_reset_tokens');
        $this->addSql('DROP TABLE identity.refresh_tokens');
        $this->addSql('DROP TABLE identity.api_keys');
    }
}
