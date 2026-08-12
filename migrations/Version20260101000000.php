<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema, squashed from the migrations that used to assume the base
 * tables (user, organization, budget, ...) already existed instead of creating them.
 */
final class Version20260101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE budget (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, is_active TINYINT NOT NULL, slug VARCHAR(255) NOT NULL, is_closed TINYINT NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, organization_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, deleted_by_id INT DEFAULT NULL, INDEX IDX_73F2F77B32C8A3DE (organization_id), INDEX IDX_73F2F77BB03A8386 (created_by_id), INDEX IDX_73F2F77B896DBBDE (updated_by_id), INDEX IDX_73F2F77BC76F1F52 (deleted_by_id), UNIQUE INDEX budget_slug_organization (slug, organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE budget_line (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, is_expense TINYINT NOT NULL, description LONGTEXT DEFAULT NULL, amount DOUBLE PRECISION NOT NULL, is_active TINYINT NOT NULL, attachment VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, budget_id INT DEFAULT NULL, category_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, deleted_by_id INT DEFAULT NULL, INDEX IDX_ABD0B6A636ABA6B8 (budget_id), INDEX IDX_ABD0B6A612469DE2 (category_id), INDEX IDX_ABD0B6A6B03A8386 (created_by_id), INDEX IDX_ABD0B6A6896DBBDE (updated_by_id), INDEX IDX_ABD0B6A6C76F1F52 (deleted_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, budget_id INT NOT NULL, parent_category_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, deleted_by_id INT DEFAULT NULL, INDEX IDX_64C19C136ABA6B8 (budget_id), INDEX IDX_64C19C1796A8F92 (parent_category_id), INDEX IDX_64C19C1B03A8386 (created_by_id), INDEX IDX_64C19C1896DBBDE (updated_by_id), INDEX IDX_64C19C1C76F1F52 (deleted_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invitation (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, status VARCHAR(20) DEFAULT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, hashed_token VARCHAR(100) NOT NULL, organisation_id INT NOT NULL, invited_by_id INT NOT NULL, UNIQUE INDEX UNIQ_F11D61A2BD2BA26B (hashed_token), INDEX IDX_F11D61A29E6B1585 (organisation_id), INDEX IDX_F11D61A2A7B4A7E3 (invited_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE organization (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, is_active TINYINT NOT NULL, description LONGTEXT DEFAULT NULL, slug VARCHAR(255) NOT NULL, picture VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_C1EE637C989D9B62 (slug), INDEX IDX_C1EE637CB03A8386 (created_by_id), INDEX IDX_C1EE637C896DBBDE (updated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE organization_user (organization_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_B49AE8D432C8A3DE (organization_id), INDEX IDX_B49AE8D4A76ED395 (user_id), PRIMARY KEY (organization_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transaction (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, amount DOUBLE PRECISION NOT NULL, payment_method VARCHAR(255) NOT NULL, comment VARCHAR(255) DEFAULT NULL, is_active TINYINT NOT NULL, reference VARCHAR(255) DEFAULT NULL, attachment VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, deleted_by_id INT DEFAULT NULL, INDEX IDX_723705D1B03A8386 (created_by_id), INDEX IDX_723705D1896DBBDE (updated_by_id), INDEX IDX_723705D1C76F1F52 (deleted_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transaction_budget_line (transaction_id INT NOT NULL, budget_line_id INT NOT NULL, INDEX IDX_1759A38B2FC0CB0F (transaction_id), INDEX IDX_1759A38B8FF83FA3 (budget_line_id), PRIMARY KEY (transaction_id, budget_line_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, pending_email VARCHAR(180) DEFAULT NULL, email_change_token VARCHAR(255) DEFAULT NULL, email_change_token_expires_at DATETIME DEFAULT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, is_verified TINYINT NOT NULL, firstname VARCHAR(255) DEFAULT NULL, lastname VARCHAR(255) DEFAULT NULL, profile_picture VARCHAR(255) DEFAULT NULL, picture VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77B32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77BB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77B896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77BC76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE budget_line ADD CONSTRAINT FK_ABD0B6A636ABA6B8 FOREIGN KEY (budget_id) REFERENCES budget (id)');
        $this->addSql('ALTER TABLE budget_line ADD CONSTRAINT FK_ABD0B6A612469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE budget_line ADD CONSTRAINT FK_ABD0B6A6B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE budget_line ADD CONSTRAINT FK_ABD0B6A6896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE budget_line ADD CONSTRAINT FK_ABD0B6A6C76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C136ABA6B8 FOREIGN KEY (budget_id) REFERENCES budget (id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1796A8F92 FOREIGN KEY (parent_category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1C76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A29E6B1585 FOREIGN KEY (organisation_id) REFERENCES organization (id)');
        $this->addSql('ALTER TABLE invitation ADD CONSTRAINT FK_F11D61A2A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637CB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637C896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D432C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_user ADD CONSTRAINT FK_B49AE8D4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1C76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE transaction_budget_line ADD CONSTRAINT FK_1759A38B2FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transaction (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transaction_budget_line ADD CONSTRAINT FK_1759A38B8FF83FA3 FOREIGN KEY (budget_line_id) REFERENCES budget_line (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77B32C8A3DE');
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77BB03A8386');
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77B896DBBDE');
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77BC76F1F52');
        $this->addSql('ALTER TABLE budget_line DROP FOREIGN KEY FK_ABD0B6A636ABA6B8');
        $this->addSql('ALTER TABLE budget_line DROP FOREIGN KEY FK_ABD0B6A612469DE2');
        $this->addSql('ALTER TABLE budget_line DROP FOREIGN KEY FK_ABD0B6A6B03A8386');
        $this->addSql('ALTER TABLE budget_line DROP FOREIGN KEY FK_ABD0B6A6896DBBDE');
        $this->addSql('ALTER TABLE budget_line DROP FOREIGN KEY FK_ABD0B6A6C76F1F52');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C136ABA6B8');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1796A8F92');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1B03A8386');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1896DBBDE');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1C76F1F52');
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A29E6B1585');
        $this->addSql('ALTER TABLE invitation DROP FOREIGN KEY FK_F11D61A2A7B4A7E3');
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637CB03A8386');
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637C896DBBDE');
        $this->addSql('ALTER TABLE organization_user DROP FOREIGN KEY FK_B49AE8D432C8A3DE');
        $this->addSql('ALTER TABLE organization_user DROP FOREIGN KEY FK_B49AE8D4A76ED395');
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1B03A8386');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1896DBBDE');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1C76F1F52');
        $this->addSql('ALTER TABLE transaction_budget_line DROP FOREIGN KEY FK_1759A38B2FC0CB0F');
        $this->addSql('ALTER TABLE transaction_budget_line DROP FOREIGN KEY FK_1759A38B8FF83FA3');
        $this->addSql('DROP TABLE budget');
        $this->addSql('DROP TABLE budget_line');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE invitation');
        $this->addSql('DROP TABLE organization');
        $this->addSql('DROP TABLE organization_user');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE transaction');
        $this->addSql('DROP TABLE transaction_budget_line');
        $this->addSql('DROP TABLE user');
    }
}
