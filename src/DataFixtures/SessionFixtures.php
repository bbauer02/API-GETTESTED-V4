<?php

namespace App\DataFixtures;

use App\Entity\Assessment;
use App\Entity\Embeddable\Address;
use App\Entity\EnrollmentExam;
use App\Entity\EnrollmentSession;
use App\Entity\Exam;
use App\Entity\Institute;
use App\Entity\Level;
use App\Entity\ScheduledExam;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\EnrollmentExamStatusEnum;
use App\Enum\SessionValidationEnum;
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
        /** @var Institute $institute */
        $institute = $this->getReference('institute_1', Institute::class);
        /** @var Assessment $toeic */
        $toeic = $this->getReference('assessment_toeic', Assessment::class);
        /** @var Level $levelB2 */
        $levelB2 = $this->getReference('level_B2', Level::class);
        /** @var Exam $listening */
        $listening = $this->getReference('exam_toeic_listening', Exam::class);
        /** @var User $user1 */
        $user1 = $this->getReference('user_user1', User::class);
        /** @var User $user2 */
        $user2 = $this->getReference('user_user2', User::class);
        /** @var User $inactive */
        $inactive = $this->getReference('user_inactive', User::class);

        // Session TOEIC — Institut Français (OPEN)
        $session = new Session();
        $session->setInstitute($institute);
        $session->setAssessment($toeic);
        $session->setLevel($levelB2);
        $session->setStart(new \DateTime('2026-03-01 09:00:00'));
        $session->setEnd(new \DateTime('2026-03-01 17:00:00'));
        $session->setLimitDateSubscribe(new \DateTime('2026-02-25 23:59:59'));
        $session->setPlacesAvailable(30);
        $session->setValidation(SessionValidationEnum::OPEN);
        $manager->persist($session);
        $this->addReference('session_toeic', $session);

        // ScheduledExam — TOEIC Listening
        $scheduledExam = new ScheduledExam();
        $scheduledExam->setSession($session);
        $scheduledExam->setExam($listening);
        $scheduledExam->setStartDate(new \DateTime('2026-03-01 09:00:00'));
        $scheduledExam->setRoom('Salle A');

        $address = new Address();
        $address->setAddress1('15 rue de Tokyo');
        $address->setCity('Paris');
        $address->setZipcode('75001');
        $address->setCountryCode('FR');
        $scheduledExam->setAddress($address);

        // Ajouter des examinateurs
        $scheduledExam->addExaminator($user1); // Ayaka — ADMIN de l'institut
        $scheduledExam->addExaminator($inactive); // Didier — TEACHER de Tenri (examinateur supplémentaire)

        $manager->persist($scheduledExam);
        $this->addReference('scheduled_exam_listening', $scheduledExam);

        // EnrollmentSession — Christophe inscrit
        $enrollment = new EnrollmentSession();
        $enrollment->setSession($session);
        $enrollment->setUser($user2);
        $enrollment->setRegistrationDate(new \DateTime('2026-02-15 10:00:00'));
        $manager->persist($enrollment);
        $this->addReference('enrollment_christophe', $enrollment);

        // EnrollmentExam pour Christophe — Listening
        $enrollmentExam = new EnrollmentExam();
        $enrollmentExam->setEnrollmentSession($enrollment);
        $enrollmentExam->setScheduledExam($scheduledExam);
        $enrollmentExam->setStatus(EnrollmentExamStatusEnum::REGISTERED);
        $manager->persist($enrollmentExam);
        $this->addReference('enrollment_exam_christophe_listening', $enrollmentExam);

        // EnrollmentSession — Didier inscrit (2e enrollment pour tests multi-users)
        $enrollmentDidier = new EnrollmentSession();
        $enrollmentDidier->setSession($session);
        $enrollmentDidier->setUser($inactive);
        $enrollmentDidier->setRegistrationDate(new \DateTime('2026-02-16 14:00:00'));
        $manager->persist($enrollmentDidier);
        $this->addReference('enrollment_didier', $enrollmentDidier);

        // EnrollmentExam pour Didier — Listening
        $enrollmentExamDidier = new EnrollmentExam();
        $enrollmentExamDidier->setEnrollmentSession($enrollmentDidier);
        $enrollmentExamDidier->setScheduledExam($scheduledExam);
        $enrollmentExamDidier->setStatus(EnrollmentExamStatusEnum::REGISTERED);
        $manager->persist($enrollmentExamDidier);

        // Session DRAFT — Institut Français (pour tester create/edit/delete/transition)
        $sessionDraft = new Session();
        $sessionDraft->setInstitute($institute);
        $sessionDraft->setAssessment($toeic);
        $sessionDraft->setLevel($levelB2);
        $sessionDraft->setStart(new \DateTime('2026-04-01 09:00:00'));
        $sessionDraft->setEnd(new \DateTime('2026-04-01 17:00:00'));
        $sessionDraft->setLimitDateSubscribe(new \DateTime('2026-03-25 23:59:59'));
        $sessionDraft->setPlacesAvailable(20);
        $sessionDraft->setValidation(SessionValidationEnum::DRAFT);
        $manager->persist($sessionDraft);
        $this->addReference('session_draft', $sessionDraft);

        // ScheduledExam pour la session draft
        $scheduledExamDraft = new ScheduledExam();
        $scheduledExamDraft->setSession($sessionDraft);
        $scheduledExamDraft->setExam($listening);
        $scheduledExamDraft->setStartDate(new \DateTime('2026-04-01 09:00:00'));
        $scheduledExamDraft->setRoom('Salle B');

        $addressDraft = new Address();
        $addressDraft->setAddress1('15 rue de Tokyo');
        $addressDraft->setCity('Paris');
        $addressDraft->setZipcode('75001');
        $addressDraft->setCountryCode('FR');
        $scheduledExamDraft->setAddress($addressDraft);

        $manager->persist($scheduledExamDraft);
        $this->addReference('scheduled_exam_draft', $scheduledExamDraft);

        $manager->flush();
    }
}
