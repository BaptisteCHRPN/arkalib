<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261025201139 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reallocation (id INT AUTO_INCREMENT NOT NULL, decided_at DATE NOT NULL, instance VARCHAR(20) NOT NULL, pv_reference VARCHAR(100) DEFAULT NULL, reason VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, organization_id INT NOT NULL, source_budget_id INT NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, deleted_by_id INT DEFAULT NULL, INDEX IDX_2DE9A82232C8A3DE (organization_id), INDEX IDX_2DE9A8225E353576 (source_budget_id), INDEX IDX_2DE9A822B03A8386 (created_by_id), INDEX IDX_2DE9A822896DBBDE (updated_by_id), INDEX IDX_2DE9A822C76F1F52 (deleted_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reallocation_line (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(12, 2) NOT NULL, reallocation_id INT NOT NULL, target_budget_id INT NOT NULL, INDEX IDX_39F638A3E2803BAE (reallocation_id), INDEX IDX_39F638A321FD494 (target_budget_id), UNIQUE INDEX reallocation_line_target (reallocation_id, target_budget_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reallocation ADD CONSTRAINT FK_2DE9A82232C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reallocation ADD CONSTRAINT FK_2DE9A8225E353576 FOREIGN KEY (source_budget_id) REFERENCES budget (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE reallocation ADD CONSTRAINT FK_2DE9A822B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reallocation ADD CONSTRAINT FK_2DE9A822896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reallocation ADD CONSTRAINT FK_2DE9A822C76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reallocation_line ADD CONSTRAINT FK_39F638A3E2803BAE FOREIGN KEY (reallocation_id) REFERENCES reallocation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reallocation_line ADD CONSTRAINT FK_39F638A321FD494 FOREIGN KEY (target_budget_id) REFERENCES budget (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reallocation DROP FOREIGN KEY FK_2DE9A82232C8A3DE');
        $this->addSql('ALTER TABLE reallocation DROP FOREIGN KEY FK_2DE9A8225E353576');
        $this->addSql('ALTER TABLE reallocation DROP FOREIGN KEY FK_2DE9A822B03A8386');
        $this->addSql('ALTER TABLE reallocation DROP FOREIGN KEY FK_2DE9A822896DBBDE');
        $this->addSql('ALTER TABLE reallocation DROP FOREIGN KEY FK_2DE9A822C76F1F52');
        $this->addSql('ALTER TABLE reallocation_line DROP FOREIGN KEY FK_39F638A3E2803BAE');
        $this->addSql('ALTER TABLE reallocation_line DROP FOREIGN KEY FK_39F638A321FD494');
        $this->addSql('DROP TABLE reallocation');
        $this->addSql('DROP TABLE reallocation_line');
    }
}
