<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remplace la table de jointure organization_user par l'entité d'association
 * organization_membership, qui porte le rôle du membre dans l'organisation.
 *
 * Les appartenances existantes sont reprises en Administrateur : c'est le seul
 * choix qui ne retire de droits à personne et ne laisse aucune organisation
 * sans administrateur. Les rôles plus fins seront attribués à la main ensuite.
 */
final class Version20260909160727 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rôles internes aux organisations : organization_user devient organization_membership (reprise en admin)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organization_membership (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(20) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, organization_id INT NOT NULL, user_id INT NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, INDEX IDX_6FA4406A32C8A3DE (organization_id), INDEX IDX_6FA4406AA76ED395 (user_id), INDEX IDX_6FA4406AB03A8386 (created_by_id), INDEX IDX_6FA4406A896DBBDE (updated_by_id), UNIQUE INDEX membership_organization_user (organization_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE organization_membership ADD CONSTRAINT FK_6FA4406A32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_membership ADD CONSTRAINT FK_6FA4406AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_membership ADD CONSTRAINT FK_6FA4406AB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE organization_membership ADD CONSTRAINT FK_6FA4406A896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');

        // Reprise des appartenances existantes AVANT de supprimer la table de jointure.
        // created_at reste NULL : on ne connaît pas la date d'adhésion réelle, et la
        // renseigner à maintenant ferait croire que tout le monde a rejoint le jour
        // de la migration.
        $this->addSql('INSERT INTO organization_membership (organization_id, user_id, role) SELECT organization_id, user_id, \'admin\' FROM organization_user');

        $this->addSql('ALTER TABLE organization_user DROP FOREIGN KEY `FK_B49AE8D432C8A3DE`');
        $this->addSql('ALTER TABLE organization_user DROP FOREIGN KEY `FK_B49AE8D4A76ED395`');
        $this->addSql('DROP TABLE organization_user');

        // Dérive préexistante sur category, sans rapport avec les rôles : les index
        // déclarés dans l'entité n'avaient jamais été appliqués en base.
        $this->addSql('CREATE INDEX idx_budget_parent ON category (budget_id, parent_category_id)');
        $this->addSql('ALTER TABLE category RENAME INDEX idx_64c19c136aba6b8 TO idx_budget_id');
        $this->addSql('ALTER TABLE category RENAME INDEX idx_64c19c1796a8f92 TO idx_parent_category_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organization_user (organization_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_B49AE8D432C8A3DE (organization_id), INDEX IDX_B49AE8D4A76ED395 (user_id), PRIMARY KEY (organization_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT `FK_B49AE8D432C8A3DE` FOREIGN KEY (organization_id) REFERENCES organization (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT `FK_B49AE8D4A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');

        // Restitution des appartenances (les rôles sont perdus, la table de jointure
        // ne sait pas les stocker).
        $this->addSql('INSERT INTO organization_user (organization_id, user_id) SELECT organization_id, user_id FROM organization_membership');

        $this->addSql('ALTER TABLE organization_membership DROP FOREIGN KEY FK_6FA4406A32C8A3DE');
        $this->addSql('ALTER TABLE organization_membership DROP FOREIGN KEY FK_6FA4406AA76ED395');
        $this->addSql('ALTER TABLE organization_membership DROP FOREIGN KEY FK_6FA4406AB03A8386');
        $this->addSql('ALTER TABLE organization_membership DROP FOREIGN KEY FK_6FA4406A896DBBDE');
        $this->addSql('DROP TABLE organization_membership');

        $this->addSql('DROP INDEX idx_budget_parent ON category');
        $this->addSql('ALTER TABLE category RENAME INDEX idx_budget_id TO IDX_64C19C136ABA6B8');
        $this->addSql('ALTER TABLE category RENAME INDEX idx_parent_category_id TO IDX_64C19C1796A8F92');
    }
}
