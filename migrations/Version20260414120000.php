<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chat: message kind (text/image/location), optional image path, GPS fields, nullable caption.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE message ADD kind VARCHAR(20) NOT NULL DEFAULT 'text'");
        $this->addSql('ALTER TABLE message ADD image_filename VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE message ADD latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE message ADD longitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE message ADD location_label VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE message MODIFY content LONGTEXT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP kind, DROP image_filename, DROP latitude, DROP longitude, DROP location_label');
        $this->addSql('UPDATE message SET content = \'\' WHERE content IS NULL');
        $this->addSql('ALTER TABLE message MODIFY content LONGTEXT NOT NULL');
    }
}
