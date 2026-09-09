<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute à l'invitation le rôle que l'invité obtiendra en rejoignant
 * l'organisation, choisi par l'inviteur au moment de l'envoi.
 */
final class Version20260909162115 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Rôle porté par l'invitation (invitations existantes reprises en lecteur)";
    }

    public function up(Schema $schema): void
    {
        // En trois temps plutôt qu'un ADD ... NOT NULL direct : celui-ci
        // remplirait les lignes existantes avec une chaîne vide, qui n'est pas
        // une valeur valide de OrganizationRole. Doctrine lèverait une erreur
        // en relisant les invitations encore en attente.
        $this->addSql('ALTER TABLE invitation ADD role VARCHAR(20) DEFAULT NULL');
        $this->addSql("UPDATE invitation SET role = 'reader'");
        $this->addSql('ALTER TABLE invitation MODIFY role VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation DROP role');
    }
}
