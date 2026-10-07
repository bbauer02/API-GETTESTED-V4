<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use App\Entity\EnrollmentSession;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\SessionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * PATCH information, transfert, refund-preview, send-sms, vérification téléphone.
 */
class EnrollmentManagementTest extends WebTestCase
{
    use ApiTestTrait;

    private function christopheEnrollment(EntityManagerInterface $em): EnrollmentSession
    {
        return $em->getRepository(EnrollmentSession::class)->findOneBy([
            'user' => $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]),
        ]);
    }

    public function testPatchInformationAsInstituteAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $enrollment = $this->christopheEnrollment($em);

        $client->request('PATCH', '/api/enrollment-sessions/' . $enrollment->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['information' => 'Tiers-temps accordé']));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Tiers-temps accordé', $data['information']);
        $this->assertArrayHasKey('phone', $data['user']);
        $this->assertArrayHasKey('phoneCountryCode', $data['user']);
        $this->assertArrayHasKey('phoneVerifiedAt', $data['user']);
    }

    public function testPatchInformationAsCandidateForbidden(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe (le candidat lui-même) ne peut pas modifier l'information
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $enrollment = $this->christopheEnrollment($em);

        $client->request('PATCH', '/api/enrollment-sessions/' . $enrollment->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['information' => 'hack']));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testRefundPreview(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $enrollment = $this->christopheEnrollment($em);

        $client->request('GET', '/api/enrollment-sessions/' . $enrollment->getId() . '/refund-preview', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(0.0, $data['paidAmount']);
        $this->assertEquals(0, $data['paymentCount']);
        $this->assertEquals('EUR', $data['currency']);
    }

    public function testTransferEnrollmentToAnotherOpenSession(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $enrollment = $this->christopheEnrollment($em);
        $sourceSessionId = (string) $enrollment->getSession()->getId();

        // La session DRAFT TOEIC du même institut est ouverte pour le test (mêmes épreuves planifiées)
        $target = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::DRAFT]);
        $target->setStatus(SessionStatusEnum::OPEN);
        $em->flush();

        $client->request('POST', '/api/enrollment-sessions/' . $enrollment->getId() . '/transfer', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['targetSessionId' => (string) $target->getId()]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals((string) $target->getId(), $data['session']['id']);
        $this->assertNotEmpty($data['enrollmentExams']);

        $targetScheduledIds = array_map(static fn ($se) => (string) $se->getId(), $target->getScheduledExams()->toArray());
        foreach ($data['enrollmentExams'] as $enrollmentExam) {
            $this->assertContains($enrollmentExam['scheduledExam']['id'], $targetScheduledIds);
        }

        // Retransférer vers la même session → 409
        $client->request('POST', '/api/enrollment-sessions/' . $enrollment->getId() . '/transfer', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['targetSessionId' => (string) $target->getId()]));
        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        // Session cible d'un autre assessment → 422
        $other = $em->getRepository(Session::class)->createQueryBuilder('s')
            ->where('s.id NOT IN (:ids)')
            ->setParameter('ids', [$sourceSessionId, (string) $target->getId()])
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        $client->request('POST', '/api/enrollment-sessions/' . $enrollment->getId() . '/transfer', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['targetSessionId' => (string) $other->getId()]));
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testTransferMissingScheduledExamReturns422WithDetail(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $enrollment = $this->christopheEnrollment($em);

        $target = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::DRAFT]);
        $target->setStatus(SessionStatusEnum::OPEN);
        // Retirer une épreuve planifiée de la cible
        $removed = $target->getScheduledExams()->first();
        $em->remove($removed);
        $em->flush();

        $client->request('POST', '/api/enrollment-sessions/' . $enrollment->getId() . '/transfer', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['targetSessionId' => (string) $target->getId()]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Manquante', $data['detail']);
    }

    public function testSendSmsWithNullTransport(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $enrollment = $this->christopheEnrollment($em);

        $client->request('POST', '/api/enrollment-sessions/' . $enrollment->getId() . '/send-sms', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['message' => 'Rappel : convocation demain 9h.']));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['sent']);
        $this->assertEquals('null', $data['provider']);

        // Message trop long → 422
        $client->request('POST', '/api/enrollment-sessions/' . $enrollment->getId() . '/send-sms', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['message' => str_repeat('a', 481)]));
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPhoneVerificationFlow(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/users/me/phone/send-code', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $sent = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($sent['sent']);
        // APP_ENV=test → pas de debugCode exposé
        $this->assertArrayNotHasKey('debugCode', $sent);

        // Mauvais code → 400
        $client->request('POST', '/api/users/me/phone/verify-code', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['code' => '000000']));
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        // On force un code connu pour valider le flux
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]);
        $user->setPhoneVerificationCode(password_hash('123456', PASSWORD_BCRYPT));
        $em->flush();

        $client->request('POST', '/api/users/me/phone/verify-code', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['code' => '123456']));
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertTrue(json_decode($client->getResponse()->getContent(), true)['verified']);

        $client->request('GET', '/api/users/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $me = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotNull($me['phoneVerifiedAt']);

        // Changement de téléphone → vérification réinitialisée
        $client->request('PATCH', '/api/users/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['phone' => '0611223344']));
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertNull(json_decode($client->getResponse()->getContent(), true)['phoneVerifiedAt']);
    }
}
