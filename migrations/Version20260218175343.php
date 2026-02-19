<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218175343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE country (code VARCHAR(2) NOT NULL, alpha3 VARCHAR(3) NOT NULL, name_original VARCHAR(255) NOT NULL, name_en VARCHAR(255) NOT NULL, name_fr VARCHAR(255) NOT NULL, flag VARCHAR(10) DEFAULT NULL, demonym_fr VARCHAR(100) DEFAULT NULL, demonym_en VARCHAR(100) DEFAULT NULL, PRIMARY KEY (code))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5373C966C065E6E4 ON country (alpha3)');
        $this->addSql('CREATE TABLE country_language (country_code VARCHAR(2) NOT NULL, language_code VARCHAR(3) NOT NULL, PRIMARY KEY (country_code, language_code))');
        $this->addSql('CREATE INDEX IDX_E7112008F026BB7C ON country_language (country_code)');
        $this->addSql('CREATE INDEX IDX_E7112008451CDAD4 ON country_language (language_code)');
        $this->addSql('CREATE TABLE language (code VARCHAR(3) NOT NULL, name_original VARCHAR(255) NOT NULL, name_en VARCHAR(255) NOT NULL, name_fr VARCHAR(255) NOT NULL, PRIMARY KEY (code))');
        $this->addSql('CREATE TABLE "user" (id UUID NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, civility VARCHAR(255) NOT NULL, gender VARCHAR(255) DEFAULT NULL, firstname VARCHAR(100) NOT NULL, lastname VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, phone_country_code VARCHAR(5) DEFAULT NULL, birthday DATE DEFAULT NULL, is_verified BOOLEAN NOT NULL, email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, previous_registration_number VARCHAR(50) DEFAULT NULL, platform_role VARCHAR(255) NOT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, address_address1 VARCHAR(255) DEFAULT NULL, address_address2 VARCHAR(255) DEFAULT NULL, address_zipcode VARCHAR(20) DEFAULT NULL, address_city VARCHAR(255) DEFAULT NULL, address_country_code VARCHAR(2) DEFAULT NULL, native_country_code VARCHAR(2) DEFAULT NULL, nationality_code VARCHAR(2) DEFAULT NULL, firstlanguage_code VARCHAR(3) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE INDEX IDX_8D93D649573721DC ON "user" (native_country_code)');
        $this->addSql('CREATE INDEX IDX_8D93D649BFB2B51C ON "user" (nationality_code)');
        $this->addSql('CREATE INDEX IDX_8D93D6496AB3EEEC ON "user" (firstlanguage_code)');
        $this->addSql('CREATE INDEX idx_user_deleted_at ON "user" (deleted_at)');
        $this->addSql('ALTER TABLE country_language ADD CONSTRAINT FK_E7112008F026BB7C FOREIGN KEY (country_code) REFERENCES country (code) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE country_language ADD CONSTRAINT FK_E7112008451CDAD4 FOREIGN KEY (language_code) REFERENCES language (code) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649573721DC FOREIGN KEY (native_country_code) REFERENCES country (code) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649BFB2B51C FOREIGN KEY (nationality_code) REFERENCES country (code) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D6496AB3EEEC FOREIGN KEY (firstlanguage_code) REFERENCES language (code) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE country_language DROP CONSTRAINT FK_E7112008F026BB7C');
        $this->addSql('ALTER TABLE country_language DROP CONSTRAINT FK_E7112008451CDAD4');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649573721DC');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649BFB2B51C');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D6496AB3EEEC');
        $this->addSql('DROP TABLE country');
        $this->addSql('DROP TABLE country_language');
        $this->addSql('DROP TABLE language');
        $this->addSql('DROP TABLE "user"');
    }
}
