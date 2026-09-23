<?php

declare(strict_types=1);

namespace DoctrineMigrations\Notification;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251230183743 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return '';
    }

    #[\Override]
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA IF NOT EXISTS notification');
        $this->addSql('CREATE TABLE notification.notification_preferences (id UUID NOT NULL, user_id UUID NOT NULL, notification_type VARCHAR(255) NOT NULL, enabled_channels JSON NOT NULL, is_enabled BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX unique_user_notification_type ON notification.notification_preferences (user_id, notification_type)');
        $this->addSql('CREATE TABLE notification.notifications (id UUID NOT NULL, recipient VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, body TEXT NOT NULL, type VARCHAR(255) NOT NULL, channel VARCHAR(255) NOT NULL, priority VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, is_mutable BOOLEAN DEFAULT true NOT NULL, is_read BOOLEAN DEFAULT false NOT NULL, user_id UUID DEFAULT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE notification.notification_preferences');
        $this->addSql('DROP TABLE notification.notifications');
    }
}
