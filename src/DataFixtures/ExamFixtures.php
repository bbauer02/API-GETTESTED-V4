<?php

namespace App\DataFixtures;

use App\Entity\Assessment;
use App\Entity\Embeddable\Price;
use App\Entity\Exam;
use App\Entity\Institute;
use App\Entity\InstituteExamPricing;
use App\Entity\Level;
use App\Entity\Skill;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ExamFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [AssessmentFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var Assessment $toeic */
        $toeic = $this->getReference('assessment_toeic', Assessment::class);
        /** @var Assessment $jlpt */
        $jlpt = $this->getReference('assessment_jlpt', Assessment::class);
        /** @var Assessment $custom */
        $custom = $this->getReference('assessment_custom', Assessment::class);
        /** @var Institute $institute1 */
        $institute1 = $this->getReference('institute_1', Institute::class);
        /** @var Institute $institute2 */
        $institute2 = $this->getReference('institute_2', Institute::class);

        // ============================================================
        // TOEIC — 2 épreuves obligatoires (Listening & Reading)
        // Tarif officiel ETS : ~120€ HT pour le test complet
        // ============================================================

        $toeicListening = new Exam();
        $toeicListening->setLabel('TOEIC Listening');
        $toeicListening->setAssessment($toeic);
        $toeicListening->setIsWritten(false);
        $toeicListening->setIsOption(false);
        $toeicListening->setCoeff(1);
        $toeicListening->setNbrQuestions(100);
        $toeicListening->setDuration(45);
        $toeicListening->setSuccessScore(225);
        $toeicListening->addSkill($this->getReference('skill_listening', Skill::class));
        $toeicListening->setPrice($this->createPrice(60.0, 'EUR', 20.0));
        $manager->persist($toeicListening);
        $this->addReference('exam_toeic_listening', $toeicListening);

        $toeicReading = new Exam();
        $toeicReading->setLabel('TOEIC Reading');
        $toeicReading->setAssessment($toeic);
        $toeicReading->setIsWritten(true);
        $toeicReading->setIsOption(false);
        $toeicReading->setCoeff(1);
        $toeicReading->setNbrQuestions(100);
        $toeicReading->setDuration(75);
        $toeicReading->setSuccessScore(225);
        $toeicReading->addSkill($this->getReference('skill_reading', Skill::class));
        $toeicReading->setPrice($this->createPrice(60.0, 'EUR', 20.0));
        $manager->persist($toeicReading);
        $this->addReference('exam_toeic_reading', $toeicReading);

        // ============================================================
        // JLPT N3 — 3 épreuves (Vocabulaire/Grammaire, Lecture, Écoute)
        // Tarif officiel : ~60€ HT
        // ============================================================

        $jlptVocabGrammar = new Exam();
        $jlptVocabGrammar->setLabel('JLPT N3 - Vocabulaire & Grammaire');
        $jlptVocabGrammar->setAssessment($jlpt);
        $jlptVocabGrammar->setIsWritten(true);
        $jlptVocabGrammar->setIsOption(false);
        $jlptVocabGrammar->setCoeff(1);
        $jlptVocabGrammar->setNbrQuestions(35);
        $jlptVocabGrammar->setDuration(30);
        $jlptVocabGrammar->setSuccessScore(19);
        $jlptVocabGrammar->setLevel($this->getReference('level_N3', Level::class));
        $jlptVocabGrammar->addSkill($this->getReference('skill_grammar', Skill::class));
        $jlptVocabGrammar->addSkill($this->getReference('skill_reading', Skill::class));
        $jlptVocabGrammar->setPrice($this->createPrice(20.0, 'EUR', 20.0));
        $manager->persist($jlptVocabGrammar);
        $this->addReference('exam_jlpt_vocab_grammar', $jlptVocabGrammar);

        $jlptReading = new Exam();
        $jlptReading->setLabel('JLPT N3 - Compréhension écrite');
        $jlptReading->setAssessment($jlpt);
        $jlptReading->setIsWritten(true);
        $jlptReading->setIsOption(false);
        $jlptReading->setCoeff(1);
        $jlptReading->setNbrQuestions(25);
        $jlptReading->setDuration(70);
        $jlptReading->setSuccessScore(19);
        $jlptReading->setLevel($this->getReference('level_N3', Level::class));
        $jlptReading->addSkill($this->getReference('skill_reading', Skill::class));
        $jlptReading->setPrice($this->createPrice(20.0, 'EUR', 20.0));
        $manager->persist($jlptReading);
        $this->addReference('exam_jlpt_reading', $jlptReading);

        $jlptListening = new Exam();
        $jlptListening->setLabel('JLPT N3 - Compréhension orale');
        $jlptListening->setAssessment($jlpt);
        $jlptListening->setIsWritten(false);
        $jlptListening->setIsOption(false);
        $jlptListening->setCoeff(1);
        $jlptListening->setNbrQuestions(30);
        $jlptListening->setDuration(40);
        $jlptListening->setSuccessScore(19);
        $jlptListening->setLevel($this->getReference('level_N3', Level::class));
        $jlptListening->addSkill($this->getReference('skill_listening', Skill::class));
        $jlptListening->setPrice($this->createPrice(20.0, 'EUR', 20.0));
        $manager->persist($jlptListening);
        $this->addReference('exam_jlpt_listening', $jlptListening);

        // ============================================================
        // Test personnalisé Institut Français — 2 épreuves (Rédaction obligatoire + Lecture optionnelle)
        // Tarif libre fixé par l'institut
        // ============================================================

        $customWriting = new Exam();
        $customWriting->setLabel('Expression écrite - Niveau A1/A2');
        $customWriting->setAssessment($custom);
        $customWriting->setIsWritten(true);
        $customWriting->setIsOption(false);
        $customWriting->setCoeff(2);
        $customWriting->setNbrQuestions(5);
        $customWriting->setDuration(60);
        $customWriting->setSuccessScore(50);
        $customWriting->setLevel($this->getReference('level_A1', Level::class));
        $customWriting->addSkill($this->getReference('skill_writing', Skill::class));
        $customWriting->setPrice($this->createPrice(35.0, 'EUR', 20.0));
        $manager->persist($customWriting);
        $this->addReference('exam_custom_writing', $customWriting);

        $customReading = new Exam();
        $customReading->setLabel('Compréhension écrite - Niveau A1/A2');
        $customReading->setAssessment($custom);
        $customReading->setIsWritten(true);
        $customReading->setIsOption(true);
        $customReading->setCoeff(1);
        $customReading->setNbrQuestions(20);
        $customReading->setDuration(45);
        $customReading->setSuccessScore(50);
        $customReading->setLevel($this->getReference('level_A2', Level::class));
        $customReading->addSkill($this->getReference('skill_reading', Skill::class));
        $customReading->setPrice($this->createPrice(25.0, 'EUR', 20.0));
        $manager->persist($customReading);
        $this->addReference('exam_custom_reading', $customReading);

        // ============================================================
        // Tarifs institut — tarifs spécifiques par institut
        // ============================================================

        // Institut Français : tarif majoré pour TOEIC Listening (centre agréé premium)
        $pricing1 = new InstituteExamPricing();
        $pricing1->setInstitute($institute1);
        $pricing1->setExam($toeicListening);
        $pricing1->setIsActive(true);
        $pricing1->setPrice($this->createPrice(70.0, 'EUR', 20.0));
        $manager->persist($pricing1);

        // Institut Français : tarif majoré pour TOEIC Reading
        $pricing2 = new InstituteExamPricing();
        $pricing2->setInstitute($institute1);
        $pricing2->setExam($toeicReading);
        $pricing2->setIsActive(true);
        $pricing2->setPrice($this->createPrice(70.0, 'EUR', 20.0));
        $manager->persist($pricing2);

        // Tenri : tarifs pour le JLPT (centre spécialisé japonais, moins cher)
        $pricing3 = new InstituteExamPricing();
        $pricing3->setInstitute($institute2);
        $pricing3->setExam($jlptVocabGrammar);
        $pricing3->setIsActive(true);
        $pricing3->setPrice($this->createPrice(18.0, 'EUR', 20.0));
        $manager->persist($pricing3);

        $pricing4 = new InstituteExamPricing();
        $pricing4->setInstitute($institute2);
        $pricing4->setExam($jlptReading);
        $pricing4->setIsActive(true);
        $pricing4->setPrice($this->createPrice(18.0, 'EUR', 20.0));
        $manager->persist($pricing4);

        $pricing5 = new InstituteExamPricing();
        $pricing5->setInstitute($institute2);
        $pricing5->setExam($jlptListening);
        $pricing5->setIsActive(true);
        $pricing5->setPrice($this->createPrice(18.0, 'EUR', 20.0));
        $manager->persist($pricing5);

        $manager->flush();
    }

    private function createPrice(float $amount, string $currency, float $tva): Price
    {
        $price = new Price();
        $price->setAmount($amount);
        $price->setCurrency($currency);
        $price->setTva($tva);

        return $price;
    }
}
