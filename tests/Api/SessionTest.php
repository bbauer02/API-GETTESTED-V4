<?php

namespace App\Tests\Api;

use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Exam;
use App\Entity\Institute;
use App\Entity\Session;
use App\Enum\SessionValidationEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SessionTest extends WebTestCase
{
    use ApiTestTrait;

    // ========================
    // GET public — filtrage OPEN uniquement
    // ========================

    public function testGetCollectionPublicShowsOnlyOpenSessions(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $client->request('GET', '/api/sessions', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);

        // Seules les sessions OPEN sont visibles publiquement
        foreach ($data as $session) {
            $this->assertEquals('OPEN', $session['validation']);
        }
        $this->assertNotEmpty($data);
    }

    public function testGetSessionContainsScheduledExams(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::OPEN]);

        $client->request('GET', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('scheduledExams', $data);
        $this->assertNotEmpty($data['scheduledExams']);

        $scheduled = $data['scheduledExams'][0];
        $this->assertArrayHasKey('id', $scheduled);
        $this->assertArrayHasKey('startDate', $scheduled);
        $this->assertArrayHasKey('room', $scheduled);
        $this->assertEquals('Salle A', $scheduled['room']);

        $this->assertArrayHasKey('exam', $scheduled);
        $this->assertIsArray($scheduled['exam']);
        $this->assertArrayHasKey('id', $scheduled['exam']);
        $this->assertArrayHasKey('label', $scheduled['exam']);
        $this->assertEquals('TOEIC Listening', $scheduled['exam']['label']);

        $this->assertArrayHasKey('address', $scheduled);
        $this->assertIsArray($scheduled['address']);
        $this->assertArrayHasKey('address1', $scheduled['address']);
        $this->assertEquals('15 rue de Tokyo', $scheduled['address']['address1']);
    }

    public function testGetSessionContainsEnrollments(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::OPEN]);

        $client->request('GET', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('enrollments', $data);
        $this->assertNotEmpty($data['enrollments']);

        $enrollment = $data['enrollments'][0];
        $this->assertArrayHasKey('id', $enrollment);
        $this->assertArrayHasKey('registrationDate', $enrollment);

        $this->assertArrayHasKey('user', $enrollment);
        $this->assertIsArray($enrollment['user']);
        $this->assertArrayHasKey('id', $enrollment['user']);
        $this->assertArrayHasKey('firstname', $enrollment['user']);
        $this->assertArrayHasKey('lastname', $enrollment['user']);
        $this->assertArrayHasKey('email', $enrollment['user']);
        $this->assertEquals('Christophe', $enrollment['user']['firstname']);
        $this->assertEquals('cLefebre@gmail.com', $enrollment['user']['email']);
    }

    public function testGetSessionContainsExamPricings(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::OPEN]);

        $client->request('GET', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);

        $scheduled = $data['scheduledExams'][0];

        $this->assertArrayHasKey('examPricing', $scheduled);
        $this->assertIsArray($scheduled['examPricing']);
        $this->assertArrayHasKey('id', $scheduled['examPricing']);
        $this->assertArrayHasKey('price', $scheduled['examPricing']);
        $this->assertArrayHasKey('active', $scheduled['examPricing']);
        $this->assertTrue($scheduled['examPricing']['active']);

        $price = $scheduled['examPricing']['price'];
        $this->assertIsArray($price);
        $this->assertEquals(65.0, $price['amount']);
        $this->assertEquals('EUR', $price['currency']);
        $this->assertEquals(20.0, $price['tva']);
    }

    public function testGetSessionContainsInstitute(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::OPEN]);

        $client->request('GET', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('institute', $data);
        $this->assertIsArray($data['institute']);
        $this->assertArrayHasKey('id', $data['institute']);
        $this->assertArrayHasKey('label', $data['institute']);
        $this->assertEquals('Institut Français', $data['institute']['label']);

        $this->assertArrayHasKey('assessment', $data);
        $this->assertIsArray($data['assessment']);
        $this->assertArrayHasKey('label', $data['assessment']);
        $this->assertEquals('TOEIC', $data['assessment']['label']);
    }

    // ========================
    // POST /institutes/{id}/sessions — Sous-ressource
    // ========================

    public function testCreateSessionAsInstituteAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est INSTITUTE_ADMIN de Institut Français
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/sessions', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'start' => '2026-05-01T09:00:00+00:00',
            'end' => '2026-05-01T17:00:00+00:00',
            'limitDateSubscribe' => '2026-04-25T23:59:59+00:00',
            'placesAvailable' => 25,
            'assessment' => '/api/assessments/' . $em->getRepository(Session::class)->findOneBy([])->getAssessment()->getId(),
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('DRAFT', $data['validation']);
        $this->assertEquals(25, $data['placesAvailable']);
    }

    public function testCreateSessionAsNonAdminForbidden(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        // Christophe est CUSTOMER de Tenri — pas admin
        $user2 = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]);
        $client->loginUser($user2);

        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE2_LABEL]);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/sessions', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'start' => '2026-05-01T09:00:00+00:00',
            'end' => '2026-05-01T17:00:00+00:00',
            'assessment' => '/api/assessments/' . $em->getRepository(Session::class)->findOneBy([])->getAssessment()->getId(),
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ========================
    // GET /institutes/{id}/sessions — Sous-ressource
    // ========================

    public function testGetInstituteSessionsAsAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est INSTITUTE_ADMIN de Institut Français
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('GET', '/api/institutes/' . $institute->getId() . '/sessions', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);

        // Doit voir toutes les sessions (OPEN + DRAFT)
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    // ========================
    // PATCH /sessions/{id} — Modifier une session
    // ========================

    public function testPatchSessionDraftAsInstituteAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::DRAFT]);

        $client->request('PATCH', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'placesAvailable' => 50,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(50, $data['placesAvailable']);
    }

    public function testPatchSessionOpenCannotChangeStart(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Baptiste est platform admin
        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::OPEN]);

        $client->request('PATCH', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'start' => '2026-06-01T09:00:00+00:00',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPatchSessionOpenCanIncreasePlaces(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::OPEN]);

        $client->request('PATCH', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'placesAvailable' => 50,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(50, $data['placesAvailable']);
    }

    // ========================
    // DELETE /sessions/{id} — Soft delete
    // ========================

    public function testDeleteSessionDraft(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::DRAFT]);

        $client->request('DELETE', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testDeleteSessionOpenFails(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::OPEN]);

        $client->request('DELETE', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    // ========================
    // PATCH /sessions/{id}/transition — Transitions workflow
    // ========================

    public function testTransitionDraftToOpen(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::DRAFT]);

        $client->request('PATCH', '/api/sessions/' . $session->getId() . '/transition', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'transition' => 'open',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('OPEN', $data['validation']);
    }

    public function testTransitionInvalidFails(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::OPEN]);

        $client->request('PATCH', '/api/sessions/' . $session->getId() . '/transition', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'transition' => 'open',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testTransitionAsNonAdminForbidden(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        // Christophe n'est pas admin de Institut Français
        $user2 = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]);
        $client->loginUser($user2);

        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::DRAFT]);

        $client->request('PATCH', '/api/sessions/' . $session->getId() . '/transition', [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'transition' => 'open',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testTransitionOpenToClose(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::OPEN]);

        $client->request('PATCH', '/api/sessions/' . $session->getId() . '/transition', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'transition' => 'close',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('CLOSE', $data['validation']);
    }

    public function testTransitionCancelFromDraft(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::DRAFT]);

        $client->request('PATCH', '/api/sessions/' . $session->getId() . '/transition', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'transition' => 'cancel_from_draft',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('CANCELLED', $data['validation']);
    }

    // ========================
    // POST /sessions/{id}/scheduled-exams — Sous-ressource ScheduledExam
    // ========================

    public function testCreateScheduledExamForDraftSession(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::DRAFT]);

        // Trouver un exam du même assessment
        $exam = $em->getRepository(Exam::class)->findOneBy(['assessment' => $session->getAssessment()]);

        $client->request('POST', '/api/sessions/' . $session->getId() . '/scheduled-exams', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'startDate' => '2026-04-01T10:00:00+00:00',
            'room' => 'Salle C',
            'exam' => '/api/exams/' . $exam->getId(),
            'address' => [
                'address1' => '10 rue de la Paix',
                'city' => 'Paris',
                'zipcode' => '75002',
                'countryCode' => 'FR',
            ],
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Salle C', $data['room']);
    }

    public function testGetScheduledExamsForSession(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['validation' => SessionValidationEnum::OPEN]);

        $client->request('GET', '/api/sessions/' . $session->getId() . '/scheduled-exams', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data);
    }
}
