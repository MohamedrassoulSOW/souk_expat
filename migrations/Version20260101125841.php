<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260101125841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE annonce (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(191) NOT NULL, slug VARCHAR(191) NOT NULL, description LONGTEXT NOT NULL, price DOUBLE PRECISION NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INT NOT NULL, category_id INT NOT NULL, city_id INT NOT NULL, INDEX IDX_F65593E5A76ED395 (user_id), INDEX IDX_F65593E512469DE2 (category_id), INDEX IDX_F65593E58BAC62AF (city_id), UNIQUE INDEX UNIQ_ANNONCE_SLUG (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE annonce ADD CONSTRAINT FK_F65593E5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE annonce ADD CONSTRAINT FK_F65593E512469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE annonce ADD CONSTRAINT FK_F65593E58BAC62AF FOREIGN KEY (city_id) REFERENCES city (id)');
        $this->addSql('DROP INDEX UNIQ_64C19C15E237E06 ON category');
        $this->addSql('ALTER TABLE category ADD created_at DATETIME NOT NULL, CHANGE name name VARCHAR(120) NOT NULL, CHANGE slug slug VARCHAR(120) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_64C19C1989D9B62 ON category (slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE annonce DROP FOREIGN KEY FK_F65593E5A76ED395');
        $this->addSql('ALTER TABLE annonce DROP FOREIGN KEY FK_F65593E512469DE2');
        $this->addSql('ALTER TABLE annonce DROP FOREIGN KEY FK_F65593E58BAC62AF');
        $this->addSql('DROP TABLE annonce');
        $this->addSql('DROP INDEX UNIQ_64C19C1989D9B62 ON category');
        $this->addSql('ALTER TABLE category DROP created_at, CHANGE name name VARCHAR(191) NOT NULL, CHANGE slug slug VARCHAR(191) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_64C19C15E237E06 ON category (name)');
    }
}
