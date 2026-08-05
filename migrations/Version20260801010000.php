<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Médias API mobile stockés en base (BLOB), plus sur le disque.
 */
final class Version20260801010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store mobile media blobs in DB (annonce_image + message)';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['annonce_image'])) {
            $cols = $sm->listTableColumns('annonce_image');
            if (!isset($cols['content'])) {
                $this->addSql('ALTER TABLE annonce_image ADD content LONGBLOB DEFAULT NULL');
            }
            if (!isset($cols['mime_type'])) {
                $this->addSql('ALTER TABLE annonce_image ADD mime_type VARCHAR(100) DEFAULT NULL');
            }
            // Nom fichier disque optionnel (legacy web) — nullable pour les blobs API
            $this->addSql('ALTER TABLE annonce_image CHANGE imade_name imade_name VARCHAR(255) DEFAULT NULL');
        }

        if ($sm->tablesExist(['message'])) {
            $cols = $sm->listTableColumns('message');
            if (!isset($cols['image_content'])) {
                $this->addSql('ALTER TABLE message ADD image_content LONGBLOB DEFAULT NULL');
            }
            if (!isset($cols['image_mime_type'])) {
                $this->addSql('ALTER TABLE message ADD image_mime_type VARCHAR(100) DEFAULT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        if ($sm->tablesExist(['annonce_image'])) {
            $cols = $sm->listTableColumns('annonce_image');
            if (isset($cols['content'])) {
                $this->addSql('ALTER TABLE annonce_image DROP content');
            }
            if (isset($cols['mime_type'])) {
                $this->addSql('ALTER TABLE annonce_image DROP mime_type');
            }
            $this->addSql('ALTER TABLE annonce_image CHANGE imade_name imade_name VARCHAR(255) NOT NULL');
        }

        if ($sm->tablesExist(['message'])) {
            $cols = $sm->listTableColumns('message');
            if (isset($cols['image_content'])) {
                $this->addSql('ALTER TABLE message DROP image_content');
            }
            if (isset($cols['image_mime_type'])) {
                $this->addSql('ALTER TABLE message DROP image_mime_type');
            }
        }
    }
}
