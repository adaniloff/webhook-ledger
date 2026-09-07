<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907204317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename previous table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webhook_event RENAME webhook_entity;');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE webhook_entity RENAME webhook_event;');
    }
}
