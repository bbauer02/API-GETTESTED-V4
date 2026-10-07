<?php

namespace App\DataFixtures;

use App\Entity\Assessment;
use App\Entity\Embeddable\Address;
use App\Entity\Exam;
use App\Entity\Institute;
use App\Entity\Level;
use App\Entity\ScheduledExam;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\SessionStatusEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class SessionFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            AssessmentFixtures::class,
            ExamFixtures::class,
            InstituteFixtures::class,
            UserFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Dates relatives (toujours dans le futur) : les sessions restent ouvertes aux inscriptions
        $toeicLimit = (new \DateTime('+23 days'))->format('Y-m-d');
        $toeicDay = (new \DateTime('+30 days'))->format('Y-m-d');
        $jlptDay = (new \DateTime('+37 days'))->format('Y-m-d');
        $customDay = (new \DateTime('+44 days'))->format('Y-m-d');
        $draftLimit = (new \DateTime('+65 days'))->format('Y-m-d');
        $draftDay = (new \DateTime('+72 days'))->format('Y-m-d');

        // Références communes
        /** @var Institute $institutFrancais */
        $institutFrancais = $this->getReference('institute_1', Institute::class);
        /** @var Institute $tenri */
        $tenri = $this->getReference('institute_2', Institute::class);
        /** @var Assessment $toeic */
        $toeic = $this->getReference('assessment_toeic', Assessment::class);
        /** @var Assessment $jlpt */
        $jlpt = $this->getReference('assessment_jlpt', Assessment::class);
        /** @var Assessment $custom */
        $custom = $this->getReference('assessment_custom', Assessment::class);

        // Users (examinateurs)
        /** @var User $ayaka */
        $ayaka = $this->getReference('user_user1', User::class);
        /** @var User $didier */
        $didier = $this->getReference('user_inactive', User::class);

        // Exams
        /** @var Exam $toeicListening */
        $toeicListening = $this->getReference('exam_toeic_listening', Exam::class);
        /** @var Exam $toeicReading */
        $toeicReading = $this->getReference('exam_toeic_reading', Exam::class);
        /** @var Exam $jlptVocabGrammar */
        $jlptVocabGrammar = $this->getReference('exam_jlpt_vocab_grammar', Exam::class);
        /** @var Exam $jlptReading */
        $jlptReading = $this->getReference('exam_jlpt_reading', Exam::class);
        /** @var Exam $jlptListening */
        $jlptListening = $this->getReference('exam_jlpt_listening', Exam::class);
        /** @var Exam $customWriting */
        $customWriting = $this->getReference('exam_custom_writing', Exam::class);
        /** @var Exam $customReading */
        $customReading = $this->getReference('exam_custom_reading', Exam::class);

        // ============================================================
        // SESSION 1 — TOEIC à l'Institut Français, OPEN
        // Date : samedi 5 avril 2026, inscriptions jusqu'au 28 mars
        // ============================================================
        $sessionToeic = new Session();
        $sessionToeic->setInstitute($institutFrancais);
        $sessionToeic->setAssessment($toeic);
        $sessionToeic->setStart(new \DateTime($toeicDay . ' 09:00:00'));
        $sessionToeic->setEnd(new \DateTime($toeicDay . ' 13:00:00'));
        $sessionToeic->setLimitDateSubscribe(new \DateTime($toeicLimit . ' 23:59:59'));
        $sessionToeic->setPlacesAvailable(25);
        $sessionToeic->setStatus(SessionStatusEnum::OPEN);
        $manager->persist($sessionToeic);
        $this->addReference('session_toeic', $sessionToeic);

        // ScheduledExam — TOEIC Listening (09h00-09h45)
        $seToeicListening = $this->createScheduledExam(
            $sessionToeic,
            $toeicListening,
            new \DateTime($toeicDay . ' 09:00:00'),
            'Salle Molière',
            '101 boulevard Raspail', 'Paris', '75006', 'FR'
        );
        $seToeicListening->addExaminator($ayaka);
        $manager->persist($seToeicListening);

        // ScheduledExam — TOEIC Reading (10h00-11h15)
        $seToeicReading = $this->createScheduledExam(
            $sessionToeic,
            $toeicReading,
            new \DateTime($toeicDay . ' 10:00:00'),
            'Salle Molière',
            '101 boulevard Raspail', 'Paris', '75006', 'FR'
        );
        $seToeicReading->addExaminator($ayaka);
        $manager->persist($seToeicReading);

        // ============================================================
        // SESSION 2 — JLPT N3 chez Tenri, OPEN
        // Date : dimanche 12 avril 2026, inscriptions jusqu'au 5 avril
        // ============================================================
        $sessionJlpt = new Session();
        $sessionJlpt->setInstitute($tenri);
        $sessionJlpt->setAssessment($jlpt);
        $sessionJlpt->setLevel($this->getReference('level_N3', Level::class));
        $sessionJlpt->setStart(new \DateTime($jlptDay . ' 13:00:00'));
        $sessionJlpt->setEnd(new \DateTime($jlptDay . ' 17:30:00'));
        $sessionJlpt->setLimitDateSubscribe(new \DateTime($toeicDay . ' 23:59:59'));
        $sessionJlpt->setPlacesAvailable(40);
        $sessionJlpt->setStatus(SessionStatusEnum::OPEN);
        $manager->persist($sessionJlpt);
        $this->addReference('session_jlpt', $sessionJlpt);

        // ScheduledExam — JLPT Vocabulaire & Grammaire (13h00-13h30)
        $seJlptVocab = $this->createScheduledExam(
            $sessionJlpt,
            $jlptVocabGrammar,
            new \DateTime($jlptDay . ' 13:00:00'),
            'Salle Sakura',
            '8-12 rue Bertin Poirée', 'Paris', '75001', 'FR'
        );
        $seJlptVocab->addExaminator($didier);
        $manager->persist($seJlptVocab);

        // ScheduledExam — JLPT Compréhension écrite (13h45-14h55)
        $seJlptReading = $this->createScheduledExam(
            $sessionJlpt,
            $jlptReading,
            new \DateTime($jlptDay . ' 13:45:00'),
            'Salle Sakura',
            '8-12 rue Bertin Poirée', 'Paris', '75001', 'FR'
        );
        $seJlptReading->addExaminator($didier);
        $manager->persist($seJlptReading);

        // ScheduledExam — JLPT Compréhension orale (15h15-15h55)
        $seJlptListening = $this->createScheduledExam(
            $sessionJlpt,
            $jlptListening,
            new \DateTime($jlptDay . ' 15:15:00'),
            'Salle Fuji',
            '8-12 rue Bertin Poirée', 'Paris', '75001', 'FR'
        );
        $seJlptListening->addExaminator($didier);
        $manager->persist($seJlptListening);

        // ============================================================
        // SESSION 3 — Test personnalisé Institut Français, OPEN
        // Date : samedi 19 avril 2026, inscriptions jusqu'au 12 avril
        // Niveau A1 — 2 épreuves (écriture obligatoire + lecture optionnelle)
        // ============================================================
        $sessionCustom = new Session();
        $sessionCustom->setInstitute($institutFrancais);
        $sessionCustom->setAssessment($custom);
        $sessionCustom->setLevel($this->getReference('level_A1', Level::class));
        $sessionCustom->setStart(new \DateTime($customDay . ' 14:00:00'));
        $sessionCustom->setEnd(new \DateTime($customDay . ' 16:30:00'));
        $sessionCustom->setLimitDateSubscribe(new \DateTime($jlptDay . ' 23:59:59'));
        $sessionCustom->setPlacesAvailable(15);
        $sessionCustom->setStatus(SessionStatusEnum::OPEN);
        $manager->persist($sessionCustom);
        $this->addReference('session_custom', $sessionCustom);

        // ScheduledExam — Expression écrite (14h00-15h00)
        $seCustomWriting = $this->createScheduledExam(
            $sessionCustom,
            $customWriting,
            new \DateTime($customDay . ' 14:00:00'),
            'Salle Victor Hugo',
            '101 boulevard Raspail', 'Paris', '75006', 'FR'
        );
        $seCustomWriting->addExaminator($ayaka);
        $manager->persist($seCustomWriting);

        // ScheduledExam — Compréhension écrite (15h15-16h00)
        $seCustomReading = $this->createScheduledExam(
            $sessionCustom,
            $customReading,
            new \DateTime($customDay . ' 15:15:00'),
            'Salle Victor Hugo',
            '101 boulevard Raspail', 'Paris', '75006', 'FR'
        );
        $seCustomReading->addExaminator($ayaka);
        $manager->persist($seCustomReading);

        // ============================================================
        // SESSION 4 — TOEIC à l'Institut Français, DRAFT
        // Date : samedi 17 mai 2026 (session en préparation)
        // ============================================================
        $sessionDraft = new Session();
        $sessionDraft->setInstitute($institutFrancais);
        $sessionDraft->setAssessment($toeic);
        $sessionDraft->setStart(new \DateTime($draftDay . ' 09:00:00'));
        $sessionDraft->setEnd(new \DateTime($draftDay . ' 13:00:00'));
        $sessionDraft->setLimitDateSubscribe(new \DateTime($draftLimit . ' 23:59:59'));
        $sessionDraft->setPlacesAvailable(30);
        $sessionDraft->setStatus(SessionStatusEnum::DRAFT);
        $manager->persist($sessionDraft);
        $this->addReference('session_draft', $sessionDraft);

        // ScheduledExam pour la session draft — Listening
        $seDraftListening = $this->createScheduledExam(
            $sessionDraft,
            $toeicListening,
            new \DateTime($draftDay . ' 09:00:00'),
            'Salle Molière',
            '101 boulevard Raspail', 'Paris', '75006', 'FR'
        );
        $manager->persist($seDraftListening);

        // ScheduledExam pour la session draft — Reading
        $seDraftReading = $this->createScheduledExam(
            $sessionDraft,
            $toeicReading,
            new \DateTime($draftDay . ' 10:00:00'),
            'Salle Molière',
            '101 boulevard Raspail', 'Paris', '75006', 'FR'
        );
        $manager->persist($seDraftReading);

        $manager->flush();
    }

    private function createScheduledExam(
        Session $session,
        Exam $exam,
        \DateTime $startDate,
        string $room,
        string $address1,
        string $city,
        string $zipcode,
        string $countryCode,
    ): ScheduledExam {
        $scheduledExam = new ScheduledExam();
        $scheduledExam->setSession($session);
        $scheduledExam->setExam($exam);
        $scheduledExam->setStartDate($startDate);
        $scheduledExam->setRoom($room);

        $address = new Address();
        $address->setAddress1($address1);
        $address->setCity($city);
        $address->setZipcode($zipcode);
        $address->setCountryCode($countryCode);
        $scheduledExam->setAddress($address);

        return $scheduledExam;
    }
}
