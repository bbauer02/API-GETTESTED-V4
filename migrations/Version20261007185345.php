<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Validation des instituts par la plateforme.
 */
final class Version20261007185345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Statut de validation des instituts (PENDING_REVIEW | ACTIVE | SUSPENDED) ; les instituts existants passent à ACTIVE.';
    }

    public function up(Schema $schema): void
    {
        // DEFAULT 'ACTIVE' : tous les instituts existants sont considérés comme validés
        $this->addSql('ALTER TABLE institute ADD status VARCHAR(20) DEFAULT \'ACTIVE\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE institute DROP status');
    }
}
