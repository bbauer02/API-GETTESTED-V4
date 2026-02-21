<?php

namespace App\DataFixtures;

use App\Entity\Assessment;
use App\Entity\Embeddable\Address;
use App\Entity\EnrollmentSession;
use App\Entity\Exam;
use App\Entity\Institute;
use App\Entity\Level;
use App\Entity\ScheduledExam;
use App\Entity\Session;
use App\Entity\User;
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
        /** @var User $user2 */
        $user2 = $this->getReference('user_user2', User::class);

        // Session TOEIC — Institut Français
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

        $manager->persist($scheduledExam);

        // EnrollmentSession — Christophe inscrit
        $enrollment = new EnrollmentSession();
        $enrollment->setSession($session);
        $enrollment->setUser($user2);
        $enrollment->setRegistrationDate(new \DateTime('2026-02-15 10:00:00'));
        $manager->persist($enrollment);

        $manager->flush();
    }
}
