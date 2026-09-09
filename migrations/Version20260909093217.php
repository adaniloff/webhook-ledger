<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909093217 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Not nullable version';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webhook_entity CHANGE version version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webhook_entity CHANGE version version INT NOT NULL');
    }
}
