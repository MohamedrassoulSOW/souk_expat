<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260104184045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, content VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, sender_id INT NOT NULL, thread_id INT NOT NULL, INDEX IDX_B6BD307FF624B39D (sender_id), INDEX IDX_B6BD307FE2904019 (thread_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE thread (id INT AUTO_INCREMENT NOT NULL, buyer_id INT NOT NULL, seller_id INT NOT NULL, annonce_id INT NOT NULL, INDEX IDX_31204C836C755722 (buyer_id), INDEX IDX_31204C838DE820D9 (seller_id), INDEX IDX_31204C838805AB2F (annonce_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FE2904019 FOREIGN KEY (thread_id) REFERENCES thread (id)');
        $this->addSql('ALTER TABLE thread ADD CONSTRAINT FK_31204C836C755722 FOREIGN KEY (buyer_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE thread ADD CONSTRAINT FK_31204C838DE820D9 FOREIGN KEY (seller_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE thread ADD CONSTRAINT FK_31204C838805AB2F FOREIGN KEY (annonce_id) REFERENCES annonce (id)');
        $this->addSql('ALTER TABLE annonce ADD CONSTRAINT FK_F65593E5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE annonce ADD CONSTRAINT FK_F65593E512469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE annonce ADD CONSTRAINT FK_F65593E58BAC62AF FOREIGN KEY (city_id) REFERENCES city (id)');
        $this->addSql('ALTER TABLE annonce_image ADD CONSTRAINT FK_D2B0CFC08805AB2F FOREIGN KEY (annonce_id) REFERENCES annonce (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF624B39D');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FE2904019');
        $this->addSql('ALTER TABLE thread DROP FOREIGN KEY FK_31204C836C755722');
        $this->addSql('ALTER TABLE thread DROP FOREIGN KEY FK_31204C838DE820D9');
        $this->addSql('ALTER TABLE thread DROP FOREIGN KEY FK_31204C838805AB2F');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE thread');
        $this->addSql('ALTER TABLE annonce DROP FOREIGN KEY FK_F65593E5A76ED395');
        $this->addSql('ALTER TABLE annonce DROP FOREIGN KEY FK_F65593E512469DE2');
        $this->addSql('ALTER TABLE annonce DROP FOREIGN KEY FK_F65593E58BAC62AF');
        $this->addSql('ALTER TABLE annonce_image DROP FOREIGN KEY FK_D2B0CFC08805AB2F');
    }
}
