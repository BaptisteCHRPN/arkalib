<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260112202451 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category ADD budget_id INT NOT NULL, ADD parent_category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C136ABA6B8 FOREIGN KEY (budget_id) REFERENCES budget (id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1796A8F92 FOREIGN KEY (parent_category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_64C19C136ABA6B8 ON category (budget_id)');
        $this->addSql('CREATE INDEX IDX_64C19C1796A8F92 ON category (parent_category_id)');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C136ABA6B8');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1796A8F92');
        $this->addSql('DROP INDEX IDX_64C19C136ABA6B8 ON category');
        $this->addSql('DROP INDEX IDX_64C19C1796A8F92 ON category');
        $this->addSql('ALTER TABLE category DROP budget_id, DROP parent_category_id');
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
    }
}
