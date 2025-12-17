<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251217112303 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_organization (user_id INT NOT NULL, organization_id INT NOT NULL, INDEX IDX_41221F7EA76ED395 (user_id), INDEX IDX_41221F7E32C8A3DE (organization_id), PRIMARY KEY (user_id, organization_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_organization ADD CONSTRAINT FK_41221F7EA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_organization ADD CONSTRAINT FK_41221F7E32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE organization_user');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77B32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE budget_line ADD CONSTRAINT FK_ABD0B6A636ABA6B8 FOREIGN KEY (budget_id) REFERENCES budget (id)');
        $this->addSql('ALTER TABLE budget_line ADD CONSTRAINT FK_ABD0B6A612469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE transaction_budget_line ADD CONSTRAINT FK_1759A38B2FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transaction (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transaction_budget_line ADD CONSTRAINT FK_1759A38B8FF83FA3 FOREIGN KEY (budget_line_id) REFERENCES budget_line (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE organization_user (organization_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_B49AE8D432C8A3DE (organization_id), INDEX IDX_B49AE8D4A76ED395 (user_id), PRIMARY KEY (organization_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE user_organization DROP FOREIGN KEY FK_41221F7EA76ED395');
        $this->addSql('ALTER TABLE user_organization DROP FOREIGN KEY FK_41221F7E32C8A3DE');
        $this->addSql('DROP TABLE user_organization');
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77B32C8A3DE');
        $this->addSql('ALTER TABLE budget_line DROP FOREIGN KEY FK_ABD0B6A636ABA6B8');
        $this->addSql('ALTER TABLE budget_line DROP FOREIGN KEY FK_ABD0B6A612469DE2');
        $this->addSql('ALTER TABLE transaction_budget_line DROP FOREIGN KEY FK_1759A38B2FC0CB0F');
        $this->addSql('ALTER TABLE transaction_budget_line DROP FOREIGN KEY FK_1759A38B8FF83FA3');
    }
}
