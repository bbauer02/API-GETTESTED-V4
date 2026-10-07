<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use App\Entity\EnrollmentExam;
use App\Entity\EnrollmentSession;
use App\Entity\Session;
use App\Enum\SessionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class EnrollmentTest extends WebTestCase
{
    use ApiTestTrait;

    // ========================
    // POST /sessions/{id}/enroll — Inscription
    // ========================

    public function testEnrollSuccess(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka s'inscrit à la session TOEIC (elle n'est pas encore inscrite)
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

        $client->request('POST', '/api/sessions/' . $session->getId() . '/enroll', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('registrationDate', $data);
        $this->assertArrayHasKey('enrollmentExams', $data);
        $this->assertNotEmpty($data['enrollmentExams']);
        $this->assertEquals('REGISTERED', $data['enrollmentExams'][0]['status']);
    }

    public function testEnrollSessionNotOpen(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::DRAFT]);

        $client->request('POST', '/api/sessions/' . $session->getId() . '/enroll', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testEnrollAlreadyEnrolled(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe est déjà inscrit à la session TOEIC
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

        $client->request('POST', '/api/sessions/' . $session->getId() . '/enroll', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testEnrollNoPlacesAvailable(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        // Réduire les places à 2 (déjà 2 inscriptions : Christophe et Didier)
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);
        $session->setPlacesAvailable(2);
        $em->flush();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/sessions/' . $session->getId() . '/enroll', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testEnrollDeadlinePassed(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        // Mettre la date limite dans le passé
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);
        $session->setLimitDateSubscribe(new \DateTime('2020-01-01'));
        $em->flush();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/sessions/' . $session->getId() . '/enroll', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testEnrollWithoutAuth(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

        $client->request('POST', '/api/sessions/' . $session->getId() . '/enroll', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    // ========================
    // GET /enrollments/{id} — Lecture
    // ========================

    public function testGetEnrollmentAsOwner(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe lit son propre enrollment
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $enrollment = $em->getRepository(EnrollmentSession::class)->findOneBy([
            'user' => $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]),
        ]);

        $client->request('GET', '/api/enrollment-sessions/' . $enrollment->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('enrollmentExams', $data);
        $this->assertNotEmpty($data['enrollmentExams']);
    }

    public function testGetEnrollmentAsNonOwnerForbidden(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        // Enrollment de Christophe
        $enrollment = $em->getRepository(EnrollmentSession::class)->findOneBy([
            'user' => $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]),
        ]);

        // Ayaka n'est pas propriétaire de cet enrollment, mais elle est ADMIN de l'institut
        // donc elle devrait avoir accès via le voter ENROLLMENT_VIEW
        // Testons avec un user qui n'a aucun rôle dans cet institut
        // Christophe est CUSTOMER de Tenri, pas de lien avec Institut Français autre qu'être inscrit
        // Utilisons Didier qui est TEACHER de Tenri mais a un enrollment ici aussi
        // En fait, testons que Didier ne peut pas voir l'enrollment de Christophe...
        // Didier est inactif, mais il a un enrollment. Utilisons plutôt un loginUser directement
        // pour un user qui n'a aucun lien.

        // En fait, Ayaka est ADMIN de l'institut, donc elle peut voir. Testons via loginUser
        // En créant un scénario où l'utilisateur n'a aucun rôle.
        // Christophe n'est pas admin/staff/teacher de Institut Français, et n'est pas propriétaire de l'enrollment de Didier
        $enrollmentDidier = $em->getRepository(EnrollmentSession::class)->findOneBy([
            'user' => $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => UserFixtures::INACTIVE_EMAIL]),
        ]);

        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('GET', '/api/enrollment-sessions/' . $enrollmentDidier->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        // Christophe n'est pas owner de l'enrollment de Didier, et n'est pas admin/staff/teacher de Institut Français
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ========================
    // GET /sessions/{id}/enrollments — Liste enrollments
    // ========================

    public function testGetSessionEnrollmentsAsAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Baptiste (platform admin) voit tous les enrollments
        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::OPEN]);

        $client->request('GET', '/api/sessions/' . $session->getId() . '/enrollments', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    /** La saisie des résultats n'est ouverte qu'une fois la session verrouillée. */
    private function scorableEnrollmentExam(EntityManagerInterface $em): EnrollmentExam
    {
        $enrollmentExam = $em->getRepository(EnrollmentExam::class)->findOneBy([]);
        $enrollmentExam->getEnrollmentSession()->getSession()->setStatus(\App\Enum\SessionStatusEnum::LOCKED);
        $em->flush();

        return $enrollmentExam;
    }

    // ========================
    // PATCH /enrollment-exams/{id}/score — Saisie de note
    // ========================

    public function testScoreAsExaminator(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est examinatrice du scheduledExam
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $enrollmentExam = $this->scorableEnrollmentExam($em);

        $client->request('PATCH', '/api/enrollment-exams/' . $enrollmentExam->getId() . '/score', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'finalScore' => 300,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(300, $data['finalScore']);
        // successScore des épreuves TOEIC = 225, donc 300 >= 225 → PASSED
        $this->assertEquals('PASSED', $data['status']);
    }

    public function testScoreFailedResult(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $enrollmentExam = $this->scorableEnrollmentExam($em);

        $client->request('PATCH', '/api/enrollment-exams/' . $enrollmentExam->getId() . '/score', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'finalScore' => 30,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(30, $data['finalScore']);
        // 30 < 225 → FAILED
        $this->assertEquals('FAILED', $data['status']);
    }

    public function testScoreAsNonExaminatorForbidden(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe n'est pas examinateur et n'est pas admin de l'institut
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $enrollmentExam = $this->scorableEnrollmentExam($em);

        $client->request('PATCH', '/api/enrollment-exams/' . $enrollmentExam->getId() . '/score', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'finalScore' => 75,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testMarkAbsentClearsScore(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $enrollmentExam = $this->scorableEnrollmentExam($em);

        $client->request('PATCH', '/api/enrollment-exams/' . $enrollmentExam->getId() . '/score', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['status' => 'ABSENT', 'finalScore' => 500]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('ABSENT', $data['status']);
        $this->assertNull($data['finalScore'] ?? null);
    }

    public function testScoreRefusedWhileSessionOpen(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $enrollmentExam = $em->getRepository(EnrollmentExam::class)->findOneBy([]);
        $enrollmentExam->getEnrollmentSession()->getSession()->setStatus(\App\Enum\SessionStatusEnum::OPEN);
        $em->flush();

        $client->request('PATCH', '/api/enrollment-exams/' . $enrollmentExam->getId() . '/score', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['finalScore' => 300]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    // ========================
    // DELETE /enrollments/{id} — Annulation
    // ========================

    public function testCancelEnrollmentAsOwner(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe annule son inscription
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $enrollment = $em->getRepository(EnrollmentSession::class)->findOneBy([
            'user' => $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]),
        ]);

        $client->request('DELETE', '/api/enrollment-sessions/' . $enrollment->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // L'inscription est conservée (historique), marquée annulée
        $em->clear();
        $cancelled = $em->getRepository(EnrollmentSession::class)->find($enrollment->getId());
        $this->assertNotNull($cancelled);
        $this->assertFalse($cancelled->isActive());
        $this->assertNotNull($cancelled->getCancelledAt());
        $this->assertNotEmpty($cancelled->getEnrollmentExams());

        // Elle n'occupe plus de place et ne compte plus comme « inscrit »
        $client->request('GET', '/api/sessions/' . $cancelled->getSession()->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $session = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($session['isEnrolledByMe']);

        // Reste visible dans l'historique du candidat
        $client->request('GET', '/api/users/me/enrollments', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $mine = json_decode($client->getResponse()->getContent(), true);
        $statuses = array_column($mine, 'status', 'id');
        $this->assertSame('CANCELLED', $statuses[(string) $enrollment->getId()] ?? null);

        // Une seconde annulation est refusée
        $client->request('DELETE', '/api/enrollment-sessions/' . $enrollment->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testCancelEnrollmentAsNonOwnerForbidden(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        // Enrollment de Didier
        $enrollmentDidier = $em->getRepository(EnrollmentSession::class)->findOneBy([
            'user' => $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => UserFixtures::INACTIVE_EMAIL]),
        ]);

        // Christophe essaie d'annuler l'enrollment de Didier
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('DELETE', '/api/enrollment-sessions/' . $enrollmentDidier->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        // Christophe n'est pas owner, pas admin institut, pas platform admin
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
