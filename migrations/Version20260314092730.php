<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260314092730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE document_template (id UUID NOT NULL, label VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, content TEXT DEFAULT NULL, is_default BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, institute_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_18A1EEDA697B0F4C ON document_template (institute_id)');
        $this->addSql('ALTER TABLE document_template ADD CONSTRAINT FK_18A1EEDA697B0F4C FOREIGN KEY (institute_id) REFERENCES institute (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document_template DROP CONSTRAINT FK_18A1EEDA697B0F4C');
        $this->addSql('DROP TABLE document_template');
    }
}
