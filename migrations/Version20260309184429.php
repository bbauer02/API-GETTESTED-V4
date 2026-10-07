<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260309184429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assessment_ownership DROP CONSTRAINT fk_cae11f78a76ed395');
        $this->addSql('DROP INDEX idx_cae11f78a76ed395');
        $this->addSql('ALTER TABLE assessment_ownership RENAME COLUMN user_id TO created_by_id');
        $this->addSql('ALTER TABLE assessment_ownership ADD CONSTRAINT FK_CAE11F78B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_CAE11F78B03A8386 ON assessment_ownership (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assessment_ownership DROP CONSTRAINT FK_CAE11F78B03A8386');
        $this->addSql('DROP INDEX IDX_CAE11F78B03A8386');
        $this->addSql('ALTER TABLE assessment_ownership RENAME COLUMN created_by_id TO user_id');
        $this->addSql('ALTER TABLE assessment_ownership ADD CONSTRAINT fk_cae11f78a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_cae11f78a76ed395 ON assessment_ownership (user_id)');
    }
}
