<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260406185337 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ability_estimate (id UUID NOT NULL, theta DOUBLE PRECISION NOT NULL, std_error DOUBLE PRECISION NOT NULL, estimated_level VARCHAR(50) NOT NULL, source VARCHAR(255) NOT NULL, skill_breakdown JSON DEFAULT NULL, estimated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, assessment_id UUID NOT NULL, level_id UUID NOT NULL, practice_session_id UUID DEFAULT NULL, enrollment_exam_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_CC977E8EA76ED395 ON ability_estimate (user_id)');
        $this->addSql('CREATE INDEX IDX_CC977E8EDD3DD5F1 ON ability_estimate (assessment_id)');
        $this->addSql('CREATE INDEX IDX_CC977E8E5FB14BA7 ON ability_estimate (level_id)');
        $this->addSql('CREATE INDEX IDX_CC977E8E4E20FDCF ON ability_estimate (practice_session_id)');
        $this->addSql('CREATE INDEX IDX_CC977E8E990235C4 ON ability_estimate (enrollment_exam_id)');
        $this->addSql('CREATE INDEX idx_ability_estimated_at ON ability_estimate (estimated_at)');
        $this->addSql('CREATE TABLE blank_slot (id UUID NOT NULL, position INT NOT NULL, accepted_answers JSON NOT NULL, case_sensitive BOOLEAN NOT NULL, accent_sensitive BOOLEAN NOT NULL, question_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_1E3D01EE1E27F6BF ON blank_slot (question_id)');
        $this->addSql('CREATE TABLE candidate_response (id UUID NOT NULL, given_answer JSON NOT NULL, status VARCHAR(255) NOT NULL, score DOUBLE PRECISION NOT NULL, response_time_ms INT NOT NULL, answered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, theta_at_answer DOUBLE PRECISION DEFAULT NULL, information_value DOUBLE PRECISION DEFAULT NULL, question_id UUID NOT NULL, subject_question_id UUID DEFAULT NULL, enrollment_exam_id UUID DEFAULT NULL, practice_session_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9F2549281E27F6BF ON candidate_response (question_id)');
        $this->addSql('CREATE INDEX IDX_9F254928862E2E9B ON candidate_response (subject_question_id)');
        $this->addSql('CREATE INDEX IDX_9F254928990235C4 ON candidate_response (enrollment_exam_id)');
        $this->addSql('CREATE INDEX IDX_9F2549284E20FDCF ON candidate_response (practice_session_id)');
        $this->addSql('CREATE INDEX idx_response_answered_at ON candidate_response (answered_at)');
        $this->addSql('CREATE TABLE choice (id UUID NOT NULL, text TEXT NOT NULL, weight DOUBLE PRECISION NOT NULL, position INT NOT NULL, feedback TEXT DEFAULT NULL, question_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C1AB5A921E27F6BF ON choice (question_id)');
        $this->addSql('CREATE TABLE fill_blank_question (blank_symbol VARCHAR(10) NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE highlight_question (id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE highlight_zone (id UUID NOT NULL, start_index INT NOT NULL, end_index INT NOT NULL, is_correct BOOLEAN NOT NULL, question_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A3B7BA631E27F6BF ON highlight_zone (question_id)');
        $this->addSql('CREATE TABLE matching_pair (id UUID NOT NULL, left_text TEXT NOT NULL, right_text TEXT NOT NULL, position INT NOT NULL, question_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F54D4BDE1E27F6BF ON matching_pair (question_id)');
        $this->addSql('CREATE TABLE matching_question (shuffle_on_display BOOLEAN NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE mcq_question (is_multiple_answer BOOLEAN NOT NULL, shuffle_choices BOOLEAN NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE media (id UUID NOT NULL, type VARCHAR(255) NOT NULL, url VARCHAR(500) NOT NULL, description VARCHAR(255) DEFAULT NULL, position INT NOT NULL, question_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6A2CA10C1E27F6BF ON media (question_id)');
        $this->addSql('CREATE TABLE ordering_item (id UUID NOT NULL, text TEXT NOT NULL, correct_position INT NOT NULL, question_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_62D0868D1E27F6BF ON ordering_item (question_id)');
        $this->addSql('CREATE TABLE ordering_question (shuffle_on_display BOOLEAN NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE practice_session (id UUID NOT NULL, status VARCHAR(255) NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, total_questions INT NOT NULL, questions_answered INT NOT NULL, initial_theta DOUBLE PRECISION NOT NULL, final_theta DOUBLE PRECISION DEFAULT NULL, final_std_error DOUBLE PRECISION DEFAULT NULL, estimated_level VARCHAR(50) DEFAULT NULL, skill_scores JSON DEFAULT NULL, max_questions INT NOT NULL, stopping_se_threshold DOUBLE PRECISION NOT NULL, user_id UUID NOT NULL, assessment_id UUID NOT NULL, level_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_DE33C9D0A76ED395 ON practice_session (user_id)');
        $this->addSql('CREATE INDEX IDX_DE33C9D0DD3DD5F1 ON practice_session (assessment_id)');
        $this->addSql('CREATE INDEX IDX_DE33C9D05FB14BA7 ON practice_session (level_id)');
        $this->addSql('CREATE TABLE question (id UUID NOT NULL, label VARCHAR(255) NOT NULL, text TEXT NOT NULL, instruction TEXT DEFAULT NULL, duration INT DEFAULT NULL, max_points DOUBLE PRECISION NOT NULL, status VARCHAR(255) NOT NULL, irt_difficulty DOUBLE PRECISION DEFAULT NULL, irt_discrimination DOUBLE PRECISION DEFAULT NULL, irt_guessing DOUBLE PRECISION DEFAULT NULL, irt_std_error DOUBLE PRECISION DEFAULT NULL, times_administered INT NOT NULL, success_rate DOUBLE PRECISION DEFAULT NULL, avg_response_time_ms INT DEFAULT NULL, is_seed_item BOOLEAN NOT NULL, retired_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, retired_reason VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, assessment_id UUID NOT NULL, level_id UUID NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B6F7494EDD3DD5F1 ON question (assessment_id)');
        $this->addSql('CREATE INDEX IDX_B6F7494E5FB14BA7 ON question (level_id)');
        $this->addSql('CREATE TABLE question_skill (question_id UUID NOT NULL, skill_id UUID NOT NULL, PRIMARY KEY (question_id, skill_id))');
        $this->addSql('CREATE INDEX IDX_6ED6F5731E27F6BF ON question_skill (question_id)');
        $this->addSql('CREATE INDEX IDX_6ED6F5735585C142 ON question_skill (skill_id)');
        $this->addSql('CREATE TABLE subject (id UUID NOT NULL, titre VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, mode VARCHAR(20) NOT NULL, passing_score DOUBLE PRECISION DEFAULT NULL, total_max_points DOUBLE PRECISION DEFAULT NULL, locked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, scheduled_exam_id UUID NOT NULL, locked_by_id UUID DEFAULT NULL, composition_rule_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FBCE3E7A7A88E00 ON subject (locked_by_id)');
        $this->addSql('CREATE INDEX IDX_FBCE3E7A69391429 ON subject (composition_rule_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_subject_scheduled_exam ON subject (scheduled_exam_id)');
        $this->addSql('CREATE TABLE subject_composition_rule (id UUID NOT NULL, total_questions INT NOT NULL, total_seed_questions INT NOT NULL, duration_minutes INT NOT NULL, skill_distribution JSON DEFAULT NULL, difficulty_distribution JSON DEFAULT NULL, min_spacing_days_global INT NOT NULL, min_spacing_days_same_institute INT NOT NULL, max_exposure_rate DOUBLE PRECISION NOT NULL, assessment_id UUID NOT NULL, level_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_EA7FEC13DD3DD5F1 ON subject_composition_rule (assessment_id)');
        $this->addSql('CREATE INDEX IDX_EA7FEC135FB14BA7 ON subject_composition_rule (level_id)');
        $this->addSql('CREATE TABLE subject_question (id UUID NOT NULL, position INT NOT NULL, is_seed BOOLEAN NOT NULL, points_override DOUBLE PRECISION DEFAULT NULL, subject_id UUID NOT NULL, question_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8E8A092623EDC87 ON subject_question (subject_id)');
        $this->addSql('CREATE INDEX IDX_8E8A09261E27F6BF ON subject_question (question_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_subject_question ON subject_question (subject_id, question_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_subject_position ON subject_question (subject_id, position)');
        $this->addSql('ALTER TABLE ability_estimate ADD CONSTRAINT FK_CC977E8EA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE ability_estimate ADD CONSTRAINT FK_CC977E8EDD3DD5F1 FOREIGN KEY (assessment_id) REFERENCES assessment (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE ability_estimate ADD CONSTRAINT FK_CC977E8E5FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE ability_estimate ADD CONSTRAINT FK_CC977E8E4E20FDCF FOREIGN KEY (practice_session_id) REFERENCES practice_session (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE ability_estimate ADD CONSTRAINT FK_CC977E8E990235C4 FOREIGN KEY (enrollment_exam_id) REFERENCES enrollment_exam (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE blank_slot ADD CONSTRAINT FK_1E3D01EE1E27F6BF FOREIGN KEY (question_id) REFERENCES fill_blank_question (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE candidate_response ADD CONSTRAINT FK_9F2549281E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE candidate_response ADD CONSTRAINT FK_9F254928862E2E9B FOREIGN KEY (subject_question_id) REFERENCES subject_question (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE candidate_response ADD CONSTRAINT FK_9F254928990235C4 FOREIGN KEY (enrollment_exam_id) REFERENCES enrollment_exam (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE candidate_response ADD CONSTRAINT FK_9F2549284E20FDCF FOREIGN KEY (practice_session_id) REFERENCES practice_session (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE choice ADD CONSTRAINT FK_C1AB5A921E27F6BF FOREIGN KEY (question_id) REFERENCES mcq_question (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE fill_blank_question ADD CONSTRAINT FK_8E899C3DBF396750 FOREIGN KEY (id) REFERENCES question (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE highlight_question ADD CONSTRAINT FK_D4D2877EBF396750 FOREIGN KEY (id) REFERENCES question (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE highlight_zone ADD CONSTRAINT FK_A3B7BA631E27F6BF FOREIGN KEY (question_id) REFERENCES highlight_question (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE matching_pair ADD CONSTRAINT FK_F54D4BDE1E27F6BF FOREIGN KEY (question_id) REFERENCES matching_question (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE matching_question ADD CONSTRAINT FK_EDADE997BF396750 FOREIGN KEY (id) REFERENCES question (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mcq_question ADD CONSTRAINT FK_63ABAD96BF396750 FOREIGN KEY (id) REFERENCES question (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10C1E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE ordering_item ADD CONSTRAINT FK_62D0868D1E27F6BF FOREIGN KEY (question_id) REFERENCES ordering_question (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE ordering_question ADD CONSTRAINT FK_C486E739BF396750 FOREIGN KEY (id) REFERENCES question (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE practice_session ADD CONSTRAINT FK_DE33C9D0A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE practice_session ADD CONSTRAINT FK_DE33C9D0DD3DD5F1 FOREIGN KEY (assessment_id) REFERENCES assessment (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE practice_session ADD CONSTRAINT FK_DE33C9D05FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494EDD3DD5F1 FOREIGN KEY (assessment_id) REFERENCES assessment (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E5FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE question_skill ADD CONSTRAINT FK_6ED6F5731E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE question_skill ADD CONSTRAINT FK_6ED6F5735585C142 FOREIGN KEY (skill_id) REFERENCES skill (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subject ADD CONSTRAINT FK_FBCE3E7A334A08D5 FOREIGN KEY (scheduled_exam_id) REFERENCES scheduled_exam (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE subject ADD CONSTRAINT FK_FBCE3E7A7A88E00 FOREIGN KEY (locked_by_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE subject ADD CONSTRAINT FK_FBCE3E7A69391429 FOREIGN KEY (composition_rule_id) REFERENCES subject_composition_rule (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE subject_composition_rule ADD CONSTRAINT FK_EA7FEC13DD3DD5F1 FOREIGN KEY (assessment_id) REFERENCES assessment (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE subject_composition_rule ADD CONSTRAINT FK_EA7FEC135FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE subject_question ADD CONSTRAINT FK_8E8A092623EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE subject_question ADD CONSTRAINT FK_8E8A09261E27F6BF FOREIGN KEY (question_id) REFERENCES question (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE session_document_publication DROP CONSTRAINT fk_session_doc_pub_session');
        $this->addSql('ALTER TABLE session_document_publication ALTER is_auto_published DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN session_document_publication.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN session_document_publication.session_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN session_document_publication.document_type_id IS \'\'');
        $this->addSql('COMMENT ON COLUMN session_document_publication.published_at IS \'\'');
        $this->addSql('ALTER TABLE session_document_publication ADD CONSTRAINT FK_47BA810613FECDF FOREIGN KEY (session_id) REFERENCES "session" (id) NOT DEFERRABLE');
        $this->addSql('ALTER INDEX idx_session_doc_pub_session RENAME TO IDX_47BA810613FECDF');
        $this->addSql('ALTER INDEX idx_session_doc_pub_doctype RENAME TO IDX_47BA81061232A4F');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ability_estimate DROP CONSTRAINT FK_CC977E8EA76ED395');
        $this->addSql('ALTER TABLE ability_estimate DROP CONSTRAINT FK_CC977E8EDD3DD5F1');
        $this->addSql('ALTER TABLE ability_estimate DROP CONSTRAINT FK_CC977E8E5FB14BA7');
        $this->addSql('ALTER TABLE ability_estimate DROP CONSTRAINT FK_CC977E8E4E20FDCF');
        $this->addSql('ALTER TABLE ability_estimate DROP CONSTRAINT FK_CC977E8E990235C4');
        $this->addSql('ALTER TABLE blank_slot DROP CONSTRAINT FK_1E3D01EE1E27F6BF');
        $this->addSql('ALTER TABLE candidate_response DROP CONSTRAINT FK_9F2549281E27F6BF');
        $this->addSql('ALTER TABLE candidate_response DROP CONSTRAINT FK_9F254928862E2E9B');
        $this->addSql('ALTER TABLE candidate_response DROP CONSTRAINT FK_9F254928990235C4');
        $this->addSql('ALTER TABLE candidate_response DROP CONSTRAINT FK_9F2549284E20FDCF');
        $this->addSql('ALTER TABLE choice DROP CONSTRAINT FK_C1AB5A921E27F6BF');
        $this->addSql('ALTER TABLE fill_blank_question DROP CONSTRAINT FK_8E899C3DBF396750');
        $this->addSql('ALTER TABLE highlight_question DROP CONSTRAINT FK_D4D2877EBF396750');
        $this->addSql('ALTER TABLE highlight_zone DROP CONSTRAINT FK_A3B7BA631E27F6BF');
        $this->addSql('ALTER TABLE matching_pair DROP CONSTRAINT FK_F54D4BDE1E27F6BF');
        $this->addSql('ALTER TABLE matching_question DROP CONSTRAINT FK_EDADE997BF396750');
        $this->addSql('ALTER TABLE mcq_question DROP CONSTRAINT FK_63ABAD96BF396750');
        $this->addSql('ALTER TABLE media DROP CONSTRAINT FK_6A2CA10C1E27F6BF');
        $this->addSql('ALTER TABLE ordering_item DROP CONSTRAINT FK_62D0868D1E27F6BF');
        $this->addSql('ALTER TABLE ordering_question DROP CONSTRAINT FK_C486E739BF396750');
        $this->addSql('ALTER TABLE practice_session DROP CONSTRAINT FK_DE33C9D0A76ED395');
        $this->addSql('ALTER TABLE practice_session DROP CONSTRAINT FK_DE33C9D0DD3DD5F1');
        $this->addSql('ALTER TABLE practice_session DROP CONSTRAINT FK_DE33C9D05FB14BA7');
        $this->addSql('ALTER TABLE question DROP CONSTRAINT FK_B6F7494EDD3DD5F1');
        $this->addSql('ALTER TABLE question DROP CONSTRAINT FK_B6F7494E5FB14BA7');
        $this->addSql('ALTER TABLE question_skill DROP CONSTRAINT FK_6ED6F5731E27F6BF');
        $this->addSql('ALTER TABLE question_skill DROP CONSTRAINT FK_6ED6F5735585C142');
        $this->addSql('ALTER TABLE subject DROP CONSTRAINT FK_FBCE3E7A334A08D5');
        $this->addSql('ALTER TABLE subject DROP CONSTRAINT FK_FBCE3E7A7A88E00');
        $this->addSql('ALTER TABLE subject DROP CONSTRAINT FK_FBCE3E7A69391429');
        $this->addSql('ALTER TABLE subject_composition_rule DROP CONSTRAINT FK_EA7FEC13DD3DD5F1');
        $this->addSql('ALTER TABLE subject_composition_rule DROP CONSTRAINT FK_EA7FEC135FB14BA7');
        $this->addSql('ALTER TABLE subject_question DROP CONSTRAINT FK_8E8A092623EDC87');
        $this->addSql('ALTER TABLE subject_question DROP CONSTRAINT FK_8E8A09261E27F6BF');
        $this->addSql('DROP TABLE ability_estimate');
        $this->addSql('DROP TABLE blank_slot');
        $this->addSql('DROP TABLE candidate_response');
        $this->addSql('DROP TABLE choice');
        $this->addSql('DROP TABLE fill_blank_question');
        $this->addSql('DROP TABLE highlight_question');
        $this->addSql('DROP TABLE highlight_zone');
        $this->addSql('DROP TABLE matching_pair');
        $this->addSql('DROP TABLE matching_question');
        $this->addSql('DROP TABLE mcq_question');
        $this->addSql('DROP TABLE media');
        $this->addSql('DROP TABLE ordering_item');
        $this->addSql('DROP TABLE ordering_question');
        $this->addSql('DROP TABLE practice_session');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE question_skill');
        $this->addSql('DROP TABLE subject');
        $this->addSql('DROP TABLE subject_composition_rule');
        $this->addSql('DROP TABLE subject_question');
        $this->addSql('ALTER TABLE session_document_publication DROP CONSTRAINT FK_47BA810613FECDF');
        $this->addSql('ALTER TABLE session_document_publication ALTER is_auto_published SET DEFAULT false');
        $this->addSql('COMMENT ON COLUMN session_document_publication.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN session_document_publication.published_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN session_document_publication.session_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN session_document_publication.document_type_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE session_document_publication ADD CONSTRAINT fk_session_doc_pub_session FOREIGN KEY (session_id) REFERENCES session (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_47ba810613fecdf RENAME TO idx_session_doc_pub_session');
        $this->addSql('ALTER INDEX idx_47ba81061232a4f RENAME TO idx_session_doc_pub_doctype');
    }
}
