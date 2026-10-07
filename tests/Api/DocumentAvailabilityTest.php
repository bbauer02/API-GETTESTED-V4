<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use App\Entity\DocumentType;
use App\Entity\EnrollmentSession;
use App\Entity\Session;
use App\Entity\SessionDocumentPublication;
use App\Entity\User;
use App\Enum\SessionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class DocumentAvailabilityTest extends WebTestCase
{
    use ApiTestTrait;

    private function openSession(EntityManagerInterface $em): Session
    {
        return $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);
    }

    private function publish(EntityManagerInterface $em, Session $session, string $code): void
    {
        $type = $em->getRepository(DocumentType::class)->findOneBy(['code' => $code]);
        $publication = new SessionDocumentPublication();
        $publication->setSession($session);
        $publication->setDocumentType($type);
        $publication->setIsAutoPublished(true);
        $publication->setPublishedAt(new \DateTimeImmutable());
        $em->persist($publication);
        $em->flush();
    }

    public function testEnrollmentDocumentsAvailabilityRules(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $session = $this->openSession($em);
        $this->publish($em, $session, 'REGISTRATION_CONFIRMATION');

        $enrollment = $em->getRepository(EnrollmentSession::class)->findOneBy([
            'user' => $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]),
        ]);

        // Le candidat propriétaire peut consulter
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $client->request('GET', '/api/enrollment-sessions/' . $enrollment->getId() . '/documents', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $byCode = [];
        foreach ($data['items'] as $item) {
            $byCode[$item['documentType']['code']] = $item;
        }

        $this->assertCount(5, $byCode);
        $this->assertTrue($byCode['REGISTRATION_CONFIRMATION']['availability']['available']);
        $this->assertNotNull($byCode['REGISTRATION_CONFIRMATION']['publication']);
        $this->assertTrue($byCode['REGISTRATION_CERTIFICATE']['availability']['available']);
        $this->assertNull($byCode['REGISTRATION_CERTIFICATE']['publication']);
        // Session OPEN → convocation indisponible
        $this->assertFalse($byCode['CONVOCATION']['availability']['available']);
        $this->assertNotNull($byCode['CONVOCATION']['availability']['reason']);
        // Épreuves dans le futur → attestation de présence indisponible avec availableFrom
        $this->assertFalse($byCode['ATTENDANCE_CERTIFICATE']['availability']['available']);
        $this->assertNotNull($byCode['ATTENDANCE_CERTIFICATE']['availability']['availableFrom']);
        // Pas de facture payée → attestation de paiement indisponible
        $this->assertFalse($byCode['PAYMENT_CERTIFICATE']['availability']['available']);
        $this->assertArrayHasKey('template', $byCode['CONVOCATION']);

        // Un utilisateur sans lien → 403
        $otherToken = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $didier = $em->getRepository(EnrollmentSession::class)->findOneBy([
            'user' => $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::INACTIVE_EMAIL]),
        ]);
        $client->request('GET', '/api/enrollment-sessions/' . $didier->getId() . '/documents', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $otherToken,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testConvocationAvailableWhenSessionValidated(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $session = $this->openSession($em);
        $session->setStatus(SessionStatusEnum::VALIDATED);
        $em->flush();
        $this->publish($em, $session, 'CONVOCATION');

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $client->request('GET', '/api/sessions/' . $session->getId() . '/document-publications', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $publications = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $publications);
        $this->assertEquals('CONVOCATION', $publications[0]['documentType']['code']);
        $this->assertTrue($publications[0]['availability']['available']);
        $this->assertNull($publications[0]['availability']['reason']);
    }

    public function testDocumentsBatchFiltersUnavailableUnlessForced(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $session = $this->openSession($em);
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        // Confirmation d'inscription : disponible pour les 2 inscrits
        $client->request('GET', '/api/sessions/' . $session->getId() . '/documents/batch?documentType=REGISTRATION_CONFIRMATION', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('REGISTRATION_CONFIRMATION', $data['documentType']['code']);
        $this->assertCount(2, $data['items']);
        $this->assertArrayHasKey('enrollment', $data['items'][0]);
        $this->assertArrayHasKey('user', $data['items'][0]['enrollment']);
        $this->assertTrue($data['items'][0]['availability']['available']);

        // Convocation (session OPEN) : aucune inscription éligible, sauf force=1
        $client->request('GET', '/api/sessions/' . $session->getId() . '/documents/batch?documentType=CONVOCATION', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(0, $data['items']);
        $this->assertCount(2, $data['skipped']);

        $enrollment = $session->getEnrollments()->first();
        $client->request('GET', '/api/sessions/' . $session->getId() . '/documents/batch?documentType=CONVOCATION&force=1&enrollmentIds=' . $enrollment->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $data['items']);
        $this->assertEquals((string) $enrollment->getId(), $data['items'][0]['enrollment']['id']);

        // Candidat (non membre) → 403
        $candidateToken = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $client->request('GET', '/api/sessions/' . $session->getId() . '/documents/batch?documentType=CONVOCATION', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $candidateToken,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
