<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415121500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Annonce: approved_at (début des 30 jours en ligne) + rattrapage sur annonces déjà approuvées.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE annonce ADD approved_at DATETIME DEFAULT NULL');
        $this->addSql("UPDATE annonce SET approved_at = created_at WHERE status = 'approved' AND approved_at IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE annonce DROP approved_at');
    }
}
