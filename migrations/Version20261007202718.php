<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007202718 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index : sessions (statut, date, suppression), inscrits actifs, factures, paiements et comptes Stripe';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_enrollment_session_status ON enrollment_session (session_id, status)');
        $this->addSql('CREATE INDEX idx_enrollment_registration_date ON enrollment_session (registration_date)');
        $this->addSql('CREATE INDEX idx_invoice_status ON invoice (status)');
        $this->addSql('CREATE INDEX idx_payment_stripe_intent ON payment (stripe_payment_intent_id)');
        $this->addSql('CREATE INDEX idx_session_status_start ON session (status, start)');
        $this->addSql('CREATE INDEX idx_session_deleted_at ON session (deleted_at)');
        $this->addSql('CREATE INDEX idx_stripe_account_stripe_id ON stripe_account (stripe_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_enrollment_session_status');
        $this->addSql('DROP INDEX idx_enrollment_registration_date');
        $this->addSql('DROP INDEX idx_invoice_status');
        $this->addSql('DROP INDEX idx_payment_stripe_intent');
        $this->addSql('DROP INDEX idx_session_status_start');
        $this->addSql('DROP INDEX idx_session_deleted_at');
        $this->addSql('DROP INDEX idx_stripe_account_stripe_id');
    }
}
