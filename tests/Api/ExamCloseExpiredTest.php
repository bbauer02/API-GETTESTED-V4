<?php

namespace App\Tests\Api;

use App\Entity\EnrollmentExam;
use App\Enum\EnrollmentExamStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Clôture automatique des épreuves en ligne : notée si commencée puis abandonnée,
 * candidat absent si jamais commencée une fois le créneau passé.
 */
class ExamCloseExpiredTest extends WebTestCase
{
    use ApiTestTrait;

    private function runCommand(string $name, array $input = []): CommandTester
    {
        $application = new Application(static::$kernel);
        $tester = new CommandTester($application->find($name));
        $tester->execute($input);

        return $tester;
    }

    /** Épreuve de démonstration (session validée, sujet verrouillé), décalée dans le passé. */
    private function demoExam(EntityManagerInterface $em, ?string $startedAgo, string $openedAgo): EnrollmentExam
    {
        $this->runCommand('app:demo:online-exam');

        $enrollmentExam = $em->getRepository(EnrollmentExam::class)->findOneBy([], ['id' => 'DESC']);
        $enrollmentExam->getScheduledExam()->setStartDate(new \DateTime($openedAgo));
        $enrollmentExam->setStartedAt($startedAgo ? new \DateTime($startedAgo) : null);
        $em->flush();

        return $enrollmentExam;
    }

    public function testAbandonedExamIsGradedAndUnstartedExamIsMarkedAbsent(): void
    {
        static::createClient();
        $this->loadFixtures();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Durée de l'épreuve de démonstration : 60 minutes
        $abandoned = $this->demoExam($em, '-2 hours', '-2 hours');
        $abandonedId = $abandoned->getId();
        $missed = $this->demoExam($em, null, '-3 hours');
        $missedId = $missed->getId();
        $running = $this->demoExam($em, '-10 minutes', '-10 minutes');
        $runningId = $running->getId();

        // Simulation : rien n'est modifié
        $tester = $this->runCommand('app:exams:close-expired', ['--dry-run' => true]);
        $tester->assertCommandIsSuccessful();
        $em->clear();
        $this->assertSame(EnrollmentExamStatusEnum::REGISTERED, $em->find(EnrollmentExam::class, $abandonedId)->getStatus());

        $this->runCommand('app:exams:close-expired')->assertCommandIsSuccessful();
        $em->clear();

        $abandoned = $em->find(EnrollmentExam::class, $abandonedId);
        $this->assertContains($abandoned->getStatus(), [EnrollmentExamStatusEnum::PASSED, EnrollmentExamStatusEnum::FAILED]);
        $this->assertNotNull($abandoned->getFinalScore());

        $missed = $em->find(EnrollmentExam::class, $missedId);
        $this->assertSame(EnrollmentExamStatusEnum::ABSENT, $missed->getStatus());
        $this->assertNull($missed->getFinalScore());

        // Épreuve en cours : intacte
        $this->assertSame(EnrollmentExamStatusEnum::REGISTERED, $em->find(EnrollmentExam::class, $runningId)->getStatus());
    }
}
