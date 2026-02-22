<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221211051 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invoice (id UUID NOT NULL, invoice_number VARCHAR(50) DEFAULT NULL, invoice_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, service_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, invoice_type VARCHAR(255) NOT NULL, business_type VARCHAR(255) NOT NULL, operation_category VARCHAR(255) NOT NULL, payment_due_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, payment_terms VARCHAR(255) DEFAULT NULL, early_payment_discount VARCHAR(255) DEFAULT NULL, late_payment_penalty_rate DOUBLE PRECISION DEFAULT NULL, fixed_recovery_indemnity DOUBLE PRECISION DEFAULT NULL, total_ht DOUBLE PRECISION NOT NULL, total_tva DOUBLE PRECISION NOT NULL, total_ttc DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(255) NOT NULL, pdf_path VARCHAR(255) DEFAULT NULL, seller_name VARCHAR(255) NOT NULL, seller_address VARCHAR(255) DEFAULT NULL, seller_city VARCHAR(100) DEFAULT NULL, seller_zipcode VARCHAR(20) DEFAULT NULL, seller_country_code VARCHAR(5) DEFAULT NULL, seller_vat_number VARCHAR(50) DEFAULT NULL, seller_siren VARCHAR(9) DEFAULT NULL, seller_siret VARCHAR(14) DEFAULT NULL, seller_legal_form VARCHAR(50) DEFAULT NULL, seller_share_capital VARCHAR(50) DEFAULT NULL, seller_rcs_city VARCHAR(100) DEFAULT NULL, buyer_name VARCHAR(255) NOT NULL, buyer_address VARCHAR(255) DEFAULT NULL, buyer_city VARCHAR(100) DEFAULT NULL, buyer_zipcode VARCHAR(20) DEFAULT NULL, buyer_country_code VARCHAR(5) DEFAULT NULL, buyer_vat_number VARCHAR(50) DEFAULT NULL, buyer_siren VARCHAR(9) DEFAULT NULL, buyer_siret VARCHAR(14) DEFAULT NULL, buyer_legal_form VARCHAR(50) DEFAULT NULL, buyer_share_capital VARCHAR(50) DEFAULT NULL, buyer_rcs_city VARCHAR(100) DEFAULT NULL, credited_invoice_id UUID DEFAULT NULL, enrollment_session_id UUID DEFAULT NULL, assessment_ownership_id UUID DEFAULT NULL, institute_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_906517442DA68207 ON invoice (invoice_number)');
        $this->addSql('CREATE INDEX IDX_906517446A17B032 ON invoice (credited_invoice_id)');
        $this->addSql('CREATE INDEX IDX_90651744F0462C97 ON invoice (enrollment_session_id)');
        $this->addSql('CREATE INDEX IDX_9065174426F42062 ON invoice (assessment_ownership_id)');
        $this->addSql('CREATE INDEX IDX_90651744697B0F4C ON invoice (institute_id)');
        $this->addSql('CREATE TABLE invoice_line (id UUID NOT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, quantity INT NOT NULL, unit_price_ht DOUBLE PRECISION NOT NULL, tva_rate DOUBLE PRECISION NOT NULL, tva_amount DOUBLE PRECISION NOT NULL, total_ht DOUBLE PRECISION NOT NULL, total_ttc DOUBLE PRECISION NOT NULL, invoice_id UUID NOT NULL, exam_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D3D1D6932989F1FD ON invoice_line (invoice_id)');
        $this->addSql('CREATE INDEX IDX_D3D1D693578D5E91 ON invoice_line (exam_id)');
        $this->addSql('CREATE TABLE payment (id UUID NOT NULL, amount DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(255) NOT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, payment_method VARCHAR(255) NOT NULL, stripe_payment_intent_id VARCHAR(255) DEFAULT NULL, refunded_payment_id UUID DEFAULT NULL, invoice_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6D28840DAA16C8A7 ON payment (refunded_payment_id)');
        $this->addSql('CREATE INDEX IDX_6D28840D2989F1FD ON payment (invoice_id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_906517446A17B032 FOREIGN KEY (credited_invoice_id) REFERENCES invoice (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744F0462C97 FOREIGN KEY (enrollment_session_id) REFERENCES enrollment_session (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_9065174426F42062 FOREIGN KEY (assessment_ownership_id) REFERENCES assessment_ownership (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744697B0F4C FOREIGN KEY (institute_id) REFERENCES institute (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE invoice_line ADD CONSTRAINT FK_D3D1D6932989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE invoice_line ADD CONSTRAINT FK_D3D1D693578D5E91 FOREIGN KEY (exam_id) REFERENCES exam (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DAA16C8A7 FOREIGN KEY (refunded_payment_id) REFERENCES payment (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE institute ADD siren VARCHAR(9) DEFAULT NULL');
        $this->addSql('ALTER TABLE institute ADD siret VARCHAR(14) DEFAULT NULL');
        $this->addSql('ALTER TABLE institute ADD legal_form VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE institute ADD share_capital VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE institute ADD rcs_city VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT FK_906517446A17B032');
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT FK_90651744F0462C97');
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT FK_9065174426F42062');
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT FK_90651744697B0F4C');
        $this->addSql('ALTER TABLE invoice_line DROP CONSTRAINT FK_D3D1D6932989F1FD');
        $this->addSql('ALTER TABLE invoice_line DROP CONSTRAINT FK_D3D1D693578D5E91');
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_6D28840DAA16C8A7');
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_6D28840D2989F1FD');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE invoice_line');
        $this->addSql('DROP TABLE payment');
        $this->addSql('ALTER TABLE institute DROP siren');
        $this->addSql('ALTER TABLE institute DROP siret');
        $this->addSql('ALTER TABLE institute DROP legal_form');
        $this->addSql('ALTER TABLE institute DROP share_capital');
        $this->addSql('ALTER TABLE institute DROP rcs_city');
    }
}
