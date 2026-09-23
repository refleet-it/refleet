<?php

declare(strict_types=1);

namespace DoctrineMigrations\Organization;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918100000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Store OAuth refresh token and access token expiry on gitlab_connections';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization.gitlab_connections ADD refresh_token_ciphertext TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE organization.gitlab_connections ADD access_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization.gitlab_connections DROP refresh_token_ciphertext');
        $this->addSql('ALTER TABLE organization.gitlab_connections DROP access_token_expires_at');
    }
}
