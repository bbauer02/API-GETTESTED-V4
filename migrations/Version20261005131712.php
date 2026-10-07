<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005131712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ExamCenter, membership status, session auto-lock, phone verification, scheduled exam center';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE exam_center (id UUID NOT NULL, label VARCHAR(255) NOT NULL, is_default BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, address_address1 VARCHAR(255) DEFAULT NULL, address_address2 VARCHAR(255) DEFAULT NULL, address_zipcode VARCHAR(20) DEFAULT NULL, address_city VARCHAR(255) DEFAULT NULL, address_country_code VARCHAR(2) DEFAULT NULL, institute_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8BC3BDAE697B0F4C ON exam_center (institute_id)');
        $this->addSql('ALTER TABLE exam_center ADD CONSTRAINT FK_8BC3BDAE697B0F4C FOREIGN KEY (institute_id) REFERENCES institute (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE institute_membership ADD status VARCHAR(255) DEFAULT \'ACTIVE\' NOT NULL');
        $this->addSql('ALTER TABLE scheduled_exam ADD exam_center_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE scheduled_exam ADD CONSTRAINT FK_F77AE1EFE6A1F62E FOREIGN KEY (exam_center_id) REFERENCES exam_center (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_F77AE1EFE6A1F62E ON scheduled_exam (exam_center_id)');
        $this->addSql('ALTER TABLE session ADD locked_automatically_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD phone_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD phone_verification_code VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD phone_verification_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exam_center DROP CONSTRAINT FK_8BC3BDAE697B0F4C');
        $this->addSql('DROP TABLE exam_center');
        $this->addSql('ALTER TABLE institute_membership DROP status');
        $this->addSql('ALTER TABLE scheduled_exam DROP CONSTRAINT FK_F77AE1EFE6A1F62E');
        $this->addSql('DROP INDEX IDX_F77AE1EFE6A1F62E');
        $this->addSql('ALTER TABLE scheduled_exam DROP exam_center_id');
        $this->addSql('ALTER TABLE "session" DROP locked_automatically_at');
        $this->addSql('ALTER TABLE "user" DROP phone_verified_at');
        $this->addSql('ALTER TABLE "user" DROP phone_verification_code');
        $this->addSql('ALTER TABLE "user" DROP phone_verification_expires_at');
    }
}
