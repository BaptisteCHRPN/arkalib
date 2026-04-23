<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260422213537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE budget ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77BB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE budget ADD CONSTRAINT FK_73F2F77B896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_73F2F77BB03A8386 ON budget (created_by_id)');
        $this->addSql('CREATE INDEX IDX_73F2F77B896DBBDE ON budget (updated_by_id)');
        $this->addSql('ALTER TABLE budget_line ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE budget_line ADD CONSTRAINT FK_ABD0B6A6B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE budget_line ADD CONSTRAINT FK_ABD0B6A6896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_ABD0B6A6B03A8386 ON budget_line (created_by_id)');
        $this->addSql('CREATE INDEX IDX_ABD0B6A6896DBBDE ON budget_line (updated_by_id)');
        $this->addSql('ALTER TABLE category ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_64C19C1B03A8386 ON category (created_by_id)');
        $this->addSql('CREATE INDEX IDX_64C19C1896DBBDE ON category (updated_by_id)');
        $this->addSql('ALTER TABLE organization ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637CB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637C896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_C1EE637CB03A8386 ON organization (created_by_id)');
        $this->addSql('CREATE INDEX IDX_C1EE637C896DBBDE ON organization (updated_by_id)');
        $this->addSql('ALTER TABLE transaction ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_723705D1B03A8386 ON transaction (created_by_id)');
        $this->addSql('CREATE INDEX IDX_723705D1896DBBDE ON transaction (updated_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77BB03A8386');
        $this->addSql('ALTER TABLE budget DROP FOREIGN KEY FK_73F2F77B896DBBDE');
        $this->addSql('DROP INDEX IDX_73F2F77BB03A8386 ON budget');
        $this->addSql('DROP INDEX IDX_73F2F77B896DBBDE ON budget');
        $this->addSql('ALTER TABLE budget DROP created_at, DROP updated_at, DROP created_by_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE budget_line DROP FOREIGN KEY FK_ABD0B6A6B03A8386');
        $this->addSql('ALTER TABLE budget_line DROP FOREIGN KEY FK_ABD0B6A6896DBBDE');
        $this->addSql('DROP INDEX IDX_ABD0B6A6B03A8386 ON budget_line');
        $this->addSql('DROP INDEX IDX_ABD0B6A6896DBBDE ON budget_line');
        $this->addSql('ALTER TABLE budget_line DROP updated_at, DROP created_by_id, DROP updated_by_id, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1B03A8386');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1896DBBDE');
        $this->addSql('DROP INDEX IDX_64C19C1B03A8386 ON category');
        $this->addSql('DROP INDEX IDX_64C19C1896DBBDE ON category');
        $this->addSql('ALTER TABLE category DROP created_at, DROP updated_at, DROP created_by_id, DROP updated_by_id');
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637CB03A8386');
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637C896DBBDE');
        $this->addSql('DROP INDEX IDX_C1EE637CB03A8386 ON organization');
        $this->addSql('DROP INDEX IDX_C1EE637C896DBBDE ON organization');
        $this->addSql('ALTER TABLE organization DROP updated_at, DROP created_by_id, DROP updated_by_id, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1B03A8386');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1896DBBDE');
        $this->addSql('DROP INDEX IDX_723705D1B03A8386 ON transaction');
        $this->addSql('DROP INDEX IDX_723705D1896DBBDE ON transaction');
        $this->addSql('ALTER TABLE transaction DROP updated_at, DROP created_by_id, DROP updated_by_id, CHANGE created_at created_at DATETIME NOT NULL');
    }
}
