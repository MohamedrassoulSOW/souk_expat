<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260111124906 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add message.is_read for unread chat badges (existing rows default to unread).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD is_read TINYINT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP is_read');
    }
}
