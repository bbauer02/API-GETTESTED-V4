<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Inscriptions annulées conservées (statut + date d'annulation) au lieu d'être supprimées.
 */
final class Version20261007200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Statut ACTIVE / CANCELLED et date d\'annulation des inscriptions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE enrollment_session ADD status VARCHAR(20) DEFAULT 'ACTIVE' NOT NULL");
        $this->addSql('ALTER TABLE enrollment_session ADD cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE enrollment_session DROP status');
        $this->addSql('ALTER TABLE enrollment_session DROP cancelled_at');
    }
}
