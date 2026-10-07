<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260311214237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stripe_account ALTER charges_enabled DROP DEFAULT');
        $this->addSql('ALTER TABLE stripe_account ALTER payouts_enabled DROP DEFAULT');
        $this->addSql('ALTER TABLE stripe_account ALTER onboarding_complete DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stripe_account ALTER charges_enabled SET DEFAULT false');
        $this->addSql('ALTER TABLE stripe_account ALTER payouts_enabled SET DEFAULT false');
        $this->addSql('ALTER TABLE stripe_account ALTER onboarding_complete SET DEFAULT false');
    }
}
