<?php

namespace App\Tests\Api;

use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Exam;
use App\Entity\Institute;
use App\Entity\Session;
use App\Enum\SessionStatusEnum;
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
            $this->assertEquals('OPEN', $session['status']);
        }
        $this->assertNotEmpty($data);
    }

    public function testGetSessionContainsScheduledExams(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

        $client->request('GET', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('scheduledExams', $data);
        $this->assertNotEmpty($data['scheduledExams']);

        $scheduled = null;
        foreach ($data['scheduledExams'] as $candidate) {
            if (($candidate['exam']['label'] ?? null) === 'TOEIC Listening') {
                $scheduled = $candidate;
            }
        }
        $this->assertNotNull($scheduled);
        $this->assertArrayHasKey('id', $scheduled);
        $this->assertArrayHasKey('startDate', $scheduled);
        $this->assertArrayHasKey('room', $scheduled);
        $this->assertEquals('Salle Molière', $scheduled['room']);

        $this->assertArrayHasKey('exam', $scheduled);
        $this->assertIsArray($scheduled['exam']);
        $this->assertArrayHasKey('id', $scheduled['exam']);
        $this->assertArrayHasKey('label', $scheduled['exam']);
        $this->assertEquals('TOEIC Listening', $scheduled['exam']['label']);

        $this->assertArrayHasKey('address', $scheduled);
        $this->assertIsArray($scheduled['address']);
        $this->assertArrayHasKey('address1', $scheduled['address']);
        $this->assertEquals('101 boulevard Raspail', $scheduled['address']['address1']);
    }

    public function testGetSessionHidesEnrollmentsFromPublic(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

        $client->request('GET', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);

        // Données personnelles des inscrits jamais exposées publiquement
        $this->assertArrayNotHasKey('enrollments', $data);
        $this->assertArrayHasKey('enrollmentsCount', $data);
        $this->assertGreaterThan(0, $data['enrollmentsCount']);
        $this->assertArrayHasKey('placesRemaining', $data);
        $this->assertFalse($data['isEnrolledByMe']);
    }

    public function testGetSessionContainsEnrollmentsForInstituteAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('GET', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
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
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

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
        $this->assertEquals(70.0, $price['amount']);
        $this->assertEquals('EUR', $price['currency']);
        $this->assertEquals(20.0, $price['tva']);
    }

    public function testGetSessionContainsInstitute(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

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
        $this->assertEquals('DRAFT', $data['status']);
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
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::DRAFT]);

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
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

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
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

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
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::DRAFT]);

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
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

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
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::DRAFT]);

        $client->request('PATCH', '/api/sessions/' . $session->getId() . '/transition', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'transition' => 'open',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('OPEN', $data['status']);
    }

    public function testTransitionInvalidFails(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

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

        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::DRAFT]);

        $client->request('PATCH', '/api/sessions/' . $session->getId() . '/transition', [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'transition' => 'open',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testTransitionOpenToLock(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

        $client->request('PATCH', '/api/sessions/' . $session->getId() . '/transition', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'transition' => 'lock',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('LOCKED', $data['status']);
        // Verrouillage manuel → pas automatique
        $this->assertFalse($data['autoLocked']);
    }

    private function transition($client, string $token, Session $session, array $payload): array
    {
        $client->request('PATCH', '/api/sessions/' . $session->getId() . '/transition', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($payload));

        return json_decode($client->getResponse()->getContent(), true) ?? [];
    }

    public function testReopenFromLockedRequiresNewFutureLimitDate(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);
        $session->setStatus(SessionStatusEnum::LOCKED);
        $session->setLockedAutomaticallyAt(new \DateTime());
        $em->flush();

        // Sans nouvelle date limite : refus explicite
        $data = $this->transition($client, $token, $session, ['transition' => 'reopen_from_locked']);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertStringContainsString('date limite', $data['detail']);

        // Avec une date limite future, avant le début de la session
        $newLimit = (clone $session->getStart())->modify('-1 day');
        $data = $this->transition($client, $token, $session, [
            'transition' => 'reopen_from_locked',
            'limitDateSubscribe' => $newLimit->format(\DateTimeInterface::ATOM),
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertEquals('OPEN', $data['status']);
        $this->assertFalse($data['autoLocked']);
    }

    public function testReopenCancelledSessionWithEnrollmentsRefused(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $session = null;
        foreach ($em->getRepository(Session::class)->findAll() as $candidate) {
            if (!$candidate->getEnrollments()->isEmpty()) {
                $session = $candidate;
                break;
            }
        }
        $session->setStatus(SessionStatusEnum::CANCELLED);
        $em->flush();

        $data = $this->transition($client, $token, $session, ['transition' => 'reopen']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertStringContainsString('nouvelle session', $data['detail']);
    }

    public function testSessionAutoLockedWhenDeadlinePassed(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);
        $session->setLimitDateSubscribe(new \DateTime('-1 day'));
        $em->flush();

        $client->request('GET', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('LOCKED', $data['status']);
        $this->assertTrue($data['autoLocked']);
        $this->assertNotNull($data['lockedAutomaticallyAt']);
    }

    public function testCancelPreviewAndCancelFromOpen(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

        $client->request('GET', '/api/sessions/' . $session->getId() . '/cancel-preview', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $preview = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(2, $preview['enrollmentCount']);
        $this->assertEquals(0, $preview['paidEnrollmentCount']);
        $this->assertEquals(0.0, $preview['refundTotal']);
        $this->assertEquals('EUR', $preview['currency']);
        $this->assertArrayHasKey('invoicesToCancel', $preview);

        $client->request('PATCH', '/api/sessions/' . $session->getId() . '/transition', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'transition' => 'cancel_from_open',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('CANCELLED', $data['status']);
        $this->assertSame([], $data['refundErrors']);
    }

    public function testTransitionCancelFromDraft(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::DRAFT]);

        $client->request('PATCH', '/api/sessions/' . $session->getId() . '/transition', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'transition' => 'cancel_from_draft',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('CANCELLED', $data['status']);
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
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::DRAFT]);

        // Trouver un exam du même assessment
        $exam = $em->getRepository(Exam::class)->findOneBy(['assessment' => $session->getAssessment()]);

        $client->request('POST', '/api/sessions/' . $session->getId() . '/scheduled-exams', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'startDate' => (clone $session->getStart())->modify('+1 hours')->format(\DateTimeInterface::ATOM),
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
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

        $client->request('GET', '/api/sessions/' . $session->getId() . '/scheduled-exams', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data);
    }
}
