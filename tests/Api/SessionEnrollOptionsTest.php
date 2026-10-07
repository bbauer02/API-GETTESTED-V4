<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use App\Entity\ScheduledExam;
use App\Entity\Session;
use App\Enum\SessionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/sessions/{id}/enroll : seules les épreuves obligatoires et les options choisies
 * sont inscrites et facturées.
 */
class SessionEnrollOptionsTest extends WebTestCase
{
    use ApiTestTrait;

    /**
     * @return array{0: Session, 1: ScheduledExam} session OPEN contenant une épreuve optionnelle
     */
    private function sessionWithOption(EntityManagerInterface $em): array
    {
        foreach ($em->getRepository(Session::class)->findBy(['status' => SessionStatusEnum::OPEN]) as $session) {
            foreach ($session->getScheduledExams() as $scheduledExam) {
                if ($scheduledExam->getExam()?->isOption()) {
                    return [$session, $scheduledExam];
                }
            }
        }

        $this->fail('Aucune session OPEN avec épreuve optionnelle dans les fixtures.');
    }

    private function enroll($client, string $email, Session $session, array $scheduledExamIds): array
    {
        $token = $this->getJwtToken($email, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/sessions/' . $session->getId() . '/enroll', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['scheduledExamIds' => $scheduledExamIds]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return json_decode($client->getResponse()->getContent(), true);
    }

    public function testOptionNotSelectedIsNeitherEnrolledNorInvoiced(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$session, $optionExam] = $this->sessionWithOption($em);
        $mandatoryCount = $session->getScheduledExams()->count() - 1;

        $data = $this->enroll($client, UserFixtures::ADMIN_EMAIL, $session, []);

        $this->assertCount($mandatoryCount, $data['enrollmentExams']);
        $enrolledIds = array_map(fn ($ee) => $ee['scheduledExam']['id'] ?? null, $data['enrollmentExams']);
        $this->assertNotContains((string) $optionExam->getId(), $enrolledIds);

        $invoiceId = $data['invoices'][0]['id'];
        $invoice = $em->getRepository(\App\Entity\Invoice::class)->find($invoiceId);
        $this->assertCount($mandatoryCount, $invoice->getLines());
        foreach ($invoice->getLines() as $line) {
            $this->assertNotSame($optionExam->getExam()->getLabel(), $line->getLabel());
        }
    }

    public function testSelectedOptionIsEnrolledAndInvoiced(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$session, $optionExam] = $this->sessionWithOption($em);

        $data = $this->enroll($client, UserFixtures::ADMIN_EMAIL, $session, [(string) $optionExam->getId()]);

        $this->assertCount($session->getScheduledExams()->count(), $data['enrollmentExams']);

        $invoice = $em->getRepository(\App\Entity\Invoice::class)->find($data['invoices'][0]['id']);
        $labels = array_map(fn ($line) => $line->getLabel(), $invoice->getLines()->toArray());
        $this->assertContains($optionExam->getExam()->getLabel(), $labels);
    }

    public function testInvoiceNumbersAreUniqueAcrossInstitutes(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Une session ouverte par institut : les UUID v7 des instituts partagent le même début
        $sessionsByInstitute = [];
        foreach ($em->getRepository(Session::class)->findBy(['status' => SessionStatusEnum::OPEN]) as $session) {
            $sessionsByInstitute[(string) $session->getInstitute()->getId()] ??= $session;
        }
        $this->assertGreaterThanOrEqual(2, count($sessionsByInstitute), 'Il faut deux instituts avec une session ouverte.');

        $numbers = [];
        foreach (array_slice($sessionsByInstitute, 0, 2) as $session) {
            $data = $this->enroll($client, UserFixtures::ADMIN_EMAIL, $session, []);
            $numbers[] = $em->getRepository(\App\Entity\Invoice::class)->find($data['invoices'][0]['id'])->getInvoiceNumber();
        }

        $this->assertCount(2, array_unique($numbers));
    }

    public function testUnknownScheduledExamIsRejected(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$session] = $this->sessionWithOption($em);

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $client->request('POST', '/api/sessions/' . $session->getId() . '/enroll', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['scheduledExamIds' => ['00000000-0000-0000-0000-000000000000']]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
