<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251212201545 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE budget RENAME INDEX idx_73f2f77b9e6b1585 TO IDX_73F2F77B32C8A3DE');
        $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D432C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_user RENAME INDEX idx_cfd7d6519e6b1585 TO IDX_B49AE8D432C8A3DE');
        $this->addSql('ALTER TABLE organization_user RENAME INDEX idx_cfd7d651a76ed395 TO IDX_B49AE8D4A76ED395');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE budget RENAME INDEX idx_73f2f77b32c8a3de TO IDX_73F2F77B9E6B1585');
        $this->addSql('ALTER TABLE organization_user DROP FOREIGN KEY FK_B49AE8D432C8A3DE');
        $this->addSql('ALTER TABLE organization_user DROP FOREIGN KEY FK_B49AE8D4A76ED395');
        $this->addSql('ALTER TABLE organization_user RENAME INDEX idx_b49ae8d432c8a3de TO IDX_CFD7D6519E6B1585');
        $this->addSql('ALTER TABLE organization_user RENAME INDEX idx_b49ae8d4a76ed395 TO IDX_CFD7D651A76ED395');
    }
}
