<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219064156 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invitation (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, status VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, hashed_token VARCHAR(100) NOT NULL, organisation_id INT NOT NULL, invited_by_id INT NOT NULL, UNIQUE INDEX UNIQ_F11D61A2BD2BA26B (hashed_token), INDEX IDX_F11D61A29E6B1585 (organisation_id), INDEX IDX_F11D61A2A7B4A7E3 (invited_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A29E6B1585 FOREIGN KEY (organisation_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A2A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE transaction ADD attachment VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A29E6B1585');
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A2A7B4A7E3');
        $this->addSql('DROP TABLE invitation');
        $this->addSql('ALTER TABLE transaction DROP attachment');
    }
}
