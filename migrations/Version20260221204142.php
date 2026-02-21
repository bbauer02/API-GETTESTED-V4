<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221204142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE enrollment_exam (id UUID NOT NULL, final_score INT DEFAULT NULL, status VARCHAR(255) NOT NULL, enrollment_session_id UUID NOT NULL, scheduled_exam_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2DF66912F0462C97 ON enrollment_exam (enrollment_session_id)');
        $this->addSql('CREATE INDEX IDX_2DF66912334A08D5 ON enrollment_exam (scheduled_exam_id)');
        $this->addSql('ALTER TABLE enrollment_exam ADD CONSTRAINT FK_2DF66912F0462C97 FOREIGN KEY (enrollment_session_id) REFERENCES enrollment_session (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE enrollment_exam ADD CONSTRAINT FK_2DF66912334A08D5 FOREIGN KEY (scheduled_exam_id) REFERENCES scheduled_exam (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE enrollment_exam DROP CONSTRAINT FK_2DF66912F0462C97');
        $this->addSql('ALTER TABLE enrollment_exam DROP CONSTRAINT FK_2DF66912334A08D5');
        $this->addSql('DROP TABLE enrollment_exam');
    }
}
