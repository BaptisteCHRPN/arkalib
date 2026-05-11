<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260511211733 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE budget ADD deleted_at DATETIME DEFAULT NULL, ADD deleted_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77BC76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_73F2F77BC76F1F52 ON budget (deleted_by_id)');
        $this->addSql('ALTER TABLE budget_line ADD deleted_at DATETIME DEFAULT NULL, ADD deleted_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE budget_line ADD CONSTRAINT FK_ABD0B6A6C76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_ABD0B6A6C76F1F52 ON budget_line (deleted_by_id)');
        $this->addSql('ALTER TABLE category ADD deleted_at DATETIME DEFAULT NULL, ADD deleted_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1C76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_64C19C1C76F1F52 ON category (deleted_by_id)');
        $this->addSql('ALTER TABLE transaction ADD deleted_at DATETIME DEFAULT NULL, ADD deleted_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1C76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_723705D1C76F1F52 ON transaction (deleted_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77BC76F1F52');
        $this->addSql('DROP INDEX IDX_73F2F77BC76F1F52 ON budget');
        $this->addSql('ALTER TABLE budget DROP deleted_at, DROP deleted_by_id');
        $this->addSql('ALTER TABLE budget_line DROP FOREIGN KEY FK_ABD0B6A6C76F1F52');
        $this->addSql('DROP INDEX IDX_ABD0B6A6C76F1F52 ON budget_line');
        $this->addSql('ALTER TABLE budget_line DROP deleted_at, DROP deleted_by_id');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1C76F1F52');
        $this->addSql('DROP INDEX IDX_64C19C1C76F1F52 ON category');
        $this->addSql('ALTER TABLE category DROP deleted_at, DROP deleted_by_id');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1C76F1F52');
        $this->addSql('DROP INDEX IDX_723705D1C76F1F52 ON transaction');
        $this->addSql('ALTER TABLE transaction DROP deleted_at, DROP deleted_by_id');
    }
}
