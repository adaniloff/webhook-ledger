<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917113043 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename webhook_entity to webhook_entry (bundle extraction), headers to JSON';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE webhook_entity TO webhook_entry');
        $this->addSql('ALTER TABLE webhook_entry MODIFY headers JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webhook_entry MODIFY headers LONGTEXT NOT NULL');
        $this->addSql('RENAME TABLE webhook_entry TO webhook_entity');
    }
}
