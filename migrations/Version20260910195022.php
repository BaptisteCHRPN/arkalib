<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260910195022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Journal des interventions des administrateurs globaux.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_action_log (id INT AUTO_INCREMENT NOT NULL, performed_at DATETIME NOT NULL, action VARCHAR(255) NOT NULL, route_name VARCHAR(255) NOT NULL, method VARCHAR(10) NOT NULL, organization_name VARCHAR(255) DEFAULT NULL, target VARCHAR(255) DEFAULT NULL, status_code INT NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, actor_id INT DEFAULT NULL, organization_id INT DEFAULT NULL, INDEX IDX_7AFB500010DAF24A (actor_id), INDEX IDX_7AFB500032C8A3DE (organization_id), INDEX idx_admin_action_performed_at (performed_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE admin_action_log ADD CONSTRAINT FK_7AFB500010DAF24A FOREIGN KEY (actor_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE admin_action_log ADD CONSTRAINT FK_7AFB500032C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_action_log DROP FOREIGN KEY FK_7AFB500010DAF24A');
        $this->addSql('ALTER TABLE admin_action_log DROP FOREIGN KEY FK_7AFB500032C8A3DE');
        $this->addSql('DROP TABLE admin_action_log');
    }
}
