<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Vide les numéros stockés sur les annonces (contact web = messagerie uniquement).
 */
final class Version20260731010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clear annonce.phone — no phone numbers stored/displayed on the website';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE annonce SET phone = '' WHERE phone IS NOT NULL AND phone <> ''");
    }

    public function down(Schema $schema): void
    {
        // Irreversible data wipe
    }
}
