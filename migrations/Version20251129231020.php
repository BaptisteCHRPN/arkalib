<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251129231020 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE user_user');
        $this->addSql('ALTER TABLE organisation_user ADD CONSTRAINT FK_CFD7D6519E6B1585 FOREIGN KEY (organisation_id) REFERENCES organisation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organisation_user ADD CONSTRAINT FK_CFD7D651A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_user (user_source INT NOT NULL, user_target INT NOT NULL, INDEX IDX_F7129A80233D34C1 (user_target), INDEX IDX_F7129A803AD8644E (user_source), PRIMARY KEY (user_source, user_target)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = \'\' ');
        $this->addSql('ALTER TABLE organisation_user DROP FOREIGN KEY FK_CFD7D6519E6B1585');
        $this->addSql('ALTER TABLE organisation_user DROP FOREIGN KEY FK_CFD7D651A76ED395');
    }
}
