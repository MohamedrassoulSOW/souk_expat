<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.whatsapp_phone for peer WhatsApp contact';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['user'])) {
            return;
        }

        $columns = $schemaManager->listTableColumns('user');
        if (isset($columns['whatsapp_phone'])) {
            return;
        }

        $this->addSql('ALTER TABLE user ADD whatsapp_phone VARCHAR(30) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['user'])) {
            return;
        }

        $columns = $schemaManager->listTableColumns('user');
        if (!isset($columns['whatsapp_phone'])) {
            return;
        }

        $this->addSql('ALTER TABLE user DROP whatsapp_phone');
    }
}
