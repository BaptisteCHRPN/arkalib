<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260909205258 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trace les comptes utilisateurs : date d\'inscription (TraceableTrait) et dernière connexion. Les comptes existants restent à NULL, aucune date ne peut être reconstituée.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user ADD last_login_at DATETIME DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD created_by_id INT DEFAULT NULL, ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_8D93D649B03A8386 ON user (created_by_id)');
        $this->addSql('CREATE INDEX IDX_8D93D649896DBBDE ON user (updated_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649B03A8386');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649896DBBDE');
        $this->addSql('DROP INDEX IDX_8D93D649B03A8386 ON user');
        $this->addSql('DROP INDEX IDX_8D93D649896DBBDE ON user');
        $this->addSql('ALTER TABLE user DROP last_login_at, DROP created_at, DROP updated_at, DROP created_by_id, DROP updated_by_id');
    }
}
