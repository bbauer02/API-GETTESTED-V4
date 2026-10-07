<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260309200201 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assessment_ownership DROP CONSTRAINT fk_cae11f78b03a8386');
        $this->addSql('DROP INDEX idx_cae11f78b03a8386');
        $this->addSql('ALTER TABLE assessment_ownership RENAME COLUMN created_by_id TO creator_id');
        $this->addSql('ALTER TABLE assessment_ownership ADD CONSTRAINT FK_CAE11F7861220EA6 FOREIGN KEY (creator_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_CAE11F7861220EA6 ON assessment_ownership (creator_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assessment_ownership DROP CONSTRAINT FK_CAE11F7861220EA6');
        $this->addSql('DROP INDEX IDX_CAE11F7861220EA6');
        $this->addSql('ALTER TABLE assessment_ownership RENAME COLUMN creator_id TO created_by_id');
        $this->addSql('ALTER TABLE assessment_ownership ADD CONSTRAINT fk_cae11f78b03a8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_cae11f78b03a8386 ON assessment_ownership (created_by_id)');
    }
}
