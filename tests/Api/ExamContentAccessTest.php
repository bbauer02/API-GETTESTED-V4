<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use App\Entity\EnrollmentExam;
use App\Entity\Institute;
use App\Entity\Session;
use App\Enum\SessionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contenus réservés au personnel des instituts : questions (avec corrigés), sujets,
 * liste des membres, lignes et paiements des factures.
 */
class ExamContentAccessTest extends WebTestCase
{
    use ApiTestTrait;

    private function get($client, string $url, string $token): array
    {
        $client->request('GET', $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        return [$client->getResponse()->getStatusCode(), json_decode($client->getResponse()->getContent(), true)];
    }

    public function testCandidateCannotReadQuestionsNorSubjects(): void
    {
        $client = static::createClient();
        $this->loadFixtures();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Épreuve en ligne de démonstration : sujet verrouillé de 3 QCM, candidat inscrit
        (new CommandTester((new Application(static::$kernel))->find('app:demo:online-exam')))->execute([]);
        $em->clear();
        $enrollmentExam = $em->getRepository(EnrollmentExam::class)->findOneBy([], ['id' => 'DESC']);
        $scheduledExam = $enrollmentExam->getScheduledExam();
        $subject = $scheduledExam->getSubject();
        $question = $subject->getSubjectQuestions()->first()->getQuestion();

        $candidate = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        [$status] = $this->get($client, '/api/questions/' . $question->getId(), $candidate);
        $this->assertContains($status, [Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND]);

        [$status, $list] = $this->get($client, '/api/questions', $candidate);
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertSame([], $list);

        [$status] = $this->get($client, '/api/scheduled-exams/' . $scheduledExam->getId() . '/subject', $candidate);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status);

        [$status] = $this->get($client, '/api/subjects/' . $subject->getId(), $candidate);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status);

        [$status] = $this->get($client, '/api/subjects/' . $subject->getId() . '/questions', $candidate);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status);

        // Le personnel de l'institut garde l'accès
        $staff = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        [$status] = $this->get($client, '/api/scheduled-exams/' . $scheduledExam->getId() . '/subject', $staff);
        $this->assertSame(Response::HTTP_OK, $status);

        [$status] = $this->get($client, '/api/subjects/' . $subject->getId() . '/questions', $staff);
        $this->assertSame(Response::HTTP_OK, $status);
    }

    public function testCandidateCannotListInstituteMembers(): void
    {
        $client = static::createClient();
        $this->loadFixtures();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $candidate = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        foreach ($em->getRepository(Institute::class)->findAll() as $institute) {
            [$status] = $this->get($client, '/api/institutes/' . $institute->getId() . '/memberships', $candidate);
            $this->assertSame(Response::HTTP_FORBIDDEN, $status, $institute->getLabel());
        }
    }

    public function testInvoiceLinesAndPaymentsFollowInvoiceAccess(): void
    {
        $client = static::createClient();
        $this->loadFixtures();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // L'admin plateforme s'inscrit à une session ouverte : une facture est créée à son nom
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN], ['start' => 'ASC']);
        $admin = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $client->request('POST', '/api/sessions/' . $session->getId() . '/enroll', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $admin,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['scheduledExamIds' => []]));
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $invoiceId = json_decode($client->getResponse()->getContent(), true)['invoices'][0]['id'];

        foreach (['lines', 'payments'] as $sub) {
            [$status] = $this->get($client, '/api/invoices/' . $invoiceId . '/' . $sub, $admin);
            $this->assertSame(Response::HTTP_OK, $status, $sub);
        }

        // Un autre candidat n'y a pas accès (ni la facture, ni ses lignes, ni ses paiements)
        $candidate = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        foreach (['', '/lines', '/payments'] as $sub) {
            [$status] = $this->get($client, '/api/invoices/' . $invoiceId . $sub, $candidate);
            $this->assertSame(Response::HTTP_FORBIDDEN, $status, $sub ?: 'invoice');
        }
    }
}
