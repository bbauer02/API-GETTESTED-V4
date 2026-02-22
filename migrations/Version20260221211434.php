<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221211434 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT fk_90651744f0462c97');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744F0462C97 FOREIGN KEY (enrollment_session_id) REFERENCES enrollment_session (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice DROP CONSTRAINT FK_90651744F0462C97');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT fk_90651744f0462c97 FOREIGN KEY (enrollment_session_id) REFERENCES enrollment_session (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
