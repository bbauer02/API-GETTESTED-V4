<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260423223259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE enrollment_session ADD reference_number VARCHAR(15) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1ECBBE818BF1AE50 ON enrollment_session (reference_number)');
        $this->addSql('ALTER TABLE "user" ADD candidate_number VARCHAR(10) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64922A34188 ON "user" (candidate_number)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_1ECBBE818BF1AE50');
        $this->addSql('ALTER TABLE enrollment_session DROP reference_number');
        $this->addSql('DROP INDEX UNIQ_8D93D64922A34188');
        $this->addSql('ALTER TABLE "user" DROP candidate_number');
    }
}
