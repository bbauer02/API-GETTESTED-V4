<?php

namespace App\DataFixtures;

use App\Entity\EnrollmentExam;
use App\Entity\EnrollmentSession;
use App\Entity\ScheduledExam;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\EnrollmentExamStatusEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Inscriptions de départ : Christophe et Didier sont inscrits à la session TOEIC (Institut Français).
 * (Pas de facture : le flow API crée les factures via InvoiceService.)
 */
class EnrollmentFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [SessionFixtures::class, UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var Session $sessionToeic */
        $sessionToeic = $this->getReference('session_toeic', Session::class);
        $scheduledExams = $manager->getRepository(ScheduledExam::class)->findBy(['session' => $sessionToeic]);

        $candidates = [
            ['user_user2', 'enrollment_christophe_toeic'],
            ['user_inactive', 'enrollment_didier_toeic'],
        ];

        foreach ($candidates as [$userRef, $enrollmentRef]) {
            /** @var User $user */
            $user = $this->getReference($userRef, User::class);

            $enrollment = new EnrollmentSession();
            $enrollment->setSession($sessionToeic);
            $enrollment->setUser($user);
            $enrollment->setRegistrationDate(new \DateTime('-2 days'));
            $manager->persist($enrollment);

            foreach ($scheduledExams as $scheduledExam) {
                $enrollmentExam = new EnrollmentExam();
                $enrollmentExam->setEnrollmentSession($enrollment);
                $enrollmentExam->setScheduledExam($scheduledExam);
                $enrollmentExam->setStatus(EnrollmentExamStatusEnum::REGISTERED);
                $manager->persist($enrollmentExam);
                $enrollment->getEnrollmentExams()->add($enrollmentExam);
            }

            $this->addReference($enrollmentRef, $enrollment);
        }

        $manager->flush();
    }
}
