<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251217112901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE organization_user (user_id INT NOT NULL, organization_id INT NOT NULL, INDEX IDX_B49AE8D4A76ED395 (user_id), INDEX IDX_B49AE8D432C8A3DE (organization_id), PRIMARY KEY (user_id, organization_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D432C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE user_organization');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_organization (user_id INT NOT NULL, organization_id INT NOT NULL, INDEX IDX_41221F7EA76ED395 (user_id), INDEX IDX_41221F7E32C8A3DE (organization_id), PRIMARY KEY (user_id, organization_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = MyISAM COMMENT = \'\' ');
        $this->addSql('ALTER TABLE organization_user DROP FOREIGN KEY FK_B49AE8D4A76ED395');
        $this->addSql('ALTER TABLE organization_user DROP FOREIGN KEY FK_B49AE8D432C8A3DE');
        $this->addSql('DROP TABLE organization_user');
    }
}
