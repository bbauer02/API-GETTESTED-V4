<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260311214107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Stripe Connect fields (status, onboarding, commission) to stripe_account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stripe_account ADD charges_enabled BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE stripe_account ADD payouts_enabled BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE stripe_account ADD onboarding_complete BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE stripe_account ADD commission_percent NUMERIC(5, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stripe_account DROP charges_enabled');
        $this->addSql('ALTER TABLE stripe_account DROP payouts_enabled');
        $this->addSql('ALTER TABLE stripe_account DROP onboarding_complete');
        $this->addSql('ALTER TABLE stripe_account DROP commission_percent');
    }
}
