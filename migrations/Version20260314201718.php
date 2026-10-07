<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260314201718 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add document_type table and migrate document_template.type enum to FK';
    }

    public function up(Schema $schema): void
    {
        // 1. Create document_type table
        $this->addSql('CREATE TABLE document_type (id UUID NOT NULL, label VARCHAR(255) NOT NULL, code VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2B6ADBBA77153098 ON document_type (code)');

        // 2. Seed document types from existing enum values
        $this->addSql("INSERT INTO document_type (id, label, code, created_at, updated_at) VALUES
            (gen_random_uuid(), 'Convocation', 'CONVOCATION', NOW(), NOW()),
            (gen_random_uuid(), 'Confirmation d''inscription', 'ENROLLMENT_CONFIRMATION', NOW(), NOW()),
            (gen_random_uuid(), 'Attestation de présence', 'ATTENDANCE_CERTIFICATE', NOW(), NOW()),
            (gen_random_uuid(), 'Attestation de paiement', 'PAYMENT_CERTIFICATE', NOW(), NOW())
        ");

        // 3. Add nullable FK column first
        $this->addSql('ALTER TABLE document_template ADD document_type_id UUID DEFAULT NULL');

        // 4. Populate FK from existing type column
        $this->addSql('UPDATE document_template SET document_type_id = dt.id FROM document_type dt WHERE dt.code = document_template.type');

        // 5. Drop old type column
        $this->addSql('ALTER TABLE document_template DROP type');

        // 6. Make FK NOT NULL
        $this->addSql('ALTER TABLE document_template ALTER document_type_id SET NOT NULL');

        // 7. Add FK constraint and index
        $this->addSql('ALTER TABLE document_template ADD CONSTRAINT FK_18A1EEDA61232A4F FOREIGN KEY (document_type_id) REFERENCES document_type (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_18A1EEDA61232A4F ON document_template (document_type_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_template DROP CONSTRAINT FK_18A1EEDA61232A4F');
        $this->addSql('DROP INDEX IDX_18A1EEDA61232A4F');
        $this->addSql('ALTER TABLE document_template ADD type VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE document_template SET type = dt.code FROM document_type dt WHERE dt.id = document_template.document_type_id');
        $this->addSql('ALTER TABLE document_template ALTER type SET NOT NULL');
        $this->addSql('ALTER TABLE document_template DROP document_type_id');
        $this->addSql('DROP TABLE document_type');
    }
}
