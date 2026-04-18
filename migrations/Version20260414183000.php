<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Category: optional image_name for visual identification on the site.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD image_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP image_name');
    }
}
