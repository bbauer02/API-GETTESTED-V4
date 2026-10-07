<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restructure session statuses (CLOSE→LOCKED, add VALIDATED) and create session_document_publication table';
    }

    public function up(Schema $schema): void
    {
        // 1. Migrate CLOSE → LOCKED
        $this->addSql("UPDATE session SET status = 'LOCKED' WHERE status = 'CLOSE'");

        // 2. Create session_document_publication table
        $this->addSql('CREATE TABLE session_document_publication (
            id UUID NOT NULL,
            session_id UUID NOT NULL,
            document_type_id UUID NOT NULL,
            is_auto_published BOOLEAN NOT NULL DEFAULT FALSE,
            published_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('COMMENT ON COLUMN session_document_publication.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN session_document_publication.session_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN session_document_publication.document_type_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN session_document_publication.published_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE UNIQUE INDEX unique_session_doctype ON session_document_publication (session_id, document_type_id)');
        $this->addSql('CREATE INDEX IDX_SESSION_DOC_PUB_SESSION ON session_document_publication (session_id)');
        $this->addSql('CREATE INDEX IDX_SESSION_DOC_PUB_DOCTYPE ON session_document_publication (document_type_id)');

        $this->addSql('ALTER TABLE session_document_publication ADD CONSTRAINT FK_SESSION_DOC_PUB_SESSION FOREIGN KEY (session_id) REFERENCES session (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE session_document_publication ADD CONSTRAINT FK_SESSION_DOC_PUB_DOCTYPE FOREIGN KEY (document_type_id) REFERENCES document_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE session_document_publication');
        $this->addSql("UPDATE session SET status = 'CLOSE' WHERE status = 'LOCKED'");
        $this->addSql("UPDATE session SET status = 'CLOSE' WHERE status = 'VALIDATED'");
    }
}
