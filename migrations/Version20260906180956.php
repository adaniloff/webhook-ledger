<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906180956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Generate the webhook_event table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<EOF
            CREATE TABLE webhook_event (
            id INT AUTO_INCREMENT NOT NULL,
            uuid CHAR(36) NOT NULL,
            source VARCHAR(255) NOT NULL,
            external_event_id VARCHAR(255) NOT NULL,
            payload LONGTEXT NOT NULL,
            headers JSON NOT NULL,
            signature_valid TINYINT NOT NULL,
            status VARCHAR(255) NOT NULL,
            attempts INT NOT NULL,
            last_error LONGTEXT DEFAULT NULL,
            received_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            version INT NOT NULL,
            UNIQUE INDEX uniq_source_external_event_id (source, external_event_id),
            PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
EOF);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE webhook_event');
    }
}
