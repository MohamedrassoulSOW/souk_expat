<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create site_settings table for dashboard-editable site content';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->tablesExist(['site_settings'])) {
            return;
        }

        $this->addSql('CREATE TABLE site_settings (
            id INT AUTO_INCREMENT NOT NULL,
            site_name VARCHAR(120) NOT NULL,
            tagline VARCHAR(255) NOT NULL,
            footer_text LONGTEXT NOT NULL,
            newsletter_text VARCHAR(255) NOT NULL,
            hero_title VARCHAR(180) NOT NULL,
            hero_subtitle VARCHAR(255) NOT NULL,
            contact_email VARCHAR(180) NOT NULL,
            contact_phone VARCHAR(40) DEFAULT NULL,
            contact_address VARCHAR(255) NOT NULL,
            contact_hours VARCHAR(120) NOT NULL,
            facebook_url VARCHAR(255) DEFAULT NULL,
            instagram_url VARCHAR(255) DEFAULT NULL,
            linkedin_url VARCHAR(255) DEFAULT NULL,
            about_heading VARCHAR(180) NOT NULL,
            about_lead VARCHAR(255) NOT NULL,
            about_body LONGTEXT NOT NULL,
            about_value1_title VARCHAR(80) NOT NULL,
            about_value1_text LONGTEXT NOT NULL,
            about_value2_title VARCHAR(80) NOT NULL,
            about_value2_text LONGTEXT NOT NULL,
            about_value3_title VARCHAR(80) NOT NULL,
            about_value3_text LONGTEXT NOT NULL,
            how_it_works_lead VARCHAR(255) NOT NULL,
            how_it_works_steps JSON NOT NULL,
            faq_lead VARCHAR(255) NOT NULL,
            faq_items JSON NOT NULL,
            legal_publisher LONGTEXT NOT NULL,
            legal_hosting LONGTEXT NOT NULL,
            legal_extra LONGTEXT DEFAULT NULL,
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS site_settings');
    }
}
