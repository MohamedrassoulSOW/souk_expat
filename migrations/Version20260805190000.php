<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add media_type to slider (image|video)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE slider ADD media_type VARCHAR(20) DEFAULT 'image' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE slider DROP media_type');
    }
}
