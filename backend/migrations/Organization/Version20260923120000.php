<?php

declare(strict_types=1);

namespace DoctrineMigrations\Organization;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Drop the GitLab webhook secret — merge request status is polled from the API now, nothing signs incoming deliveries';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization.gitlab_connections DROP webhook_secret');
    }

    /**
     * A rolled-back connection gets a fresh secret rather than its old one: the value was
     * only ever compared against what GitLab echoed back, so any 64-character secret is as
     * good as the original — and hooks configured with the old one would have to be
     * re-registered either way.
     */
    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE organization.gitlab_connections ADD webhook_secret VARCHAR(64) NOT NULL DEFAULT ''");
        // md5() twice rather than gen_random_bytes(), which needs the pgcrypto extension.
        $this->addSql('UPDATE organization.gitlab_connections SET webhook_secret = md5(random()::text) || md5(random()::text)');
        $this->addSql('ALTER TABLE organization.gitlab_connections ALTER COLUMN webhook_secret DROP DEFAULT');
    }
}
