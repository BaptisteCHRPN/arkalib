<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261026210334 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réaffectations : trace des forçages de la protection des recettes';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE revenue_protection_override (id INT AUTO_INCREMENT NOT NULL, forced_at DATETIME NOT NULL, reason VARCHAR(255) NOT NULL, operation LONGTEXT NOT NULL, reallocations LONGTEXT NOT NULL, available_after NUMERIC(12, 2) NOT NULL, organization_id INT NOT NULL, budget_id INT NOT NULL, forced_by_id INT NOT NULL, INDEX IDX_65DDD8A32C8A3DE (organization_id), INDEX IDX_65DDD8A36ABA6B8 (budget_id), INDEX IDX_65DDD8A9E2B2EAF (forced_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE revenue_protection_override ADD CONSTRAINT FK_65DDD8A32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE revenue_protection_override ADD CONSTRAINT FK_65DDD8A36ABA6B8 FOREIGN KEY (budget_id) REFERENCES budget (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE revenue_protection_override ADD CONSTRAINT FK_65DDD8A9E2B2EAF FOREIGN KEY (forced_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE revenue_protection_override DROP FOREIGN KEY FK_65DDD8A32C8A3DE');
        $this->addSql('ALTER TABLE revenue_protection_override DROP FOREIGN KEY FK_65DDD8A36ABA6B8');
        $this->addSql('ALTER TABLE revenue_protection_override DROP FOREIGN KEY FK_65DDD8A9E2B2EAF');
        $this->addSql('DROP TABLE revenue_protection_override');
    }
}
