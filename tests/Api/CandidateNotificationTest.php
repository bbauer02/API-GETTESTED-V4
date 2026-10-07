<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use App\Entity\Session;
use App\Enum\SessionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emails transactionnels envoyés aux candidats aux étapes clés.
 */
class CandidateNotificationTest extends WebTestCase
{
    use ApiTestTrait;

    public function testEnrollmentSendsConfirmationWithPaymentLink(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $session = null;
        foreach ($em->getRepository(Session::class)->findBy(['status' => SessionStatusEnum::OPEN]) as $candidate) {
            $alreadyEnrolled = $candidate->getEnrollments()->exists(
                fn ($key, $enrollment) => $enrollment->getUser()?->getEmail() === UserFixtures::ADMIN_EMAIL
            );
            if (!$alreadyEnrolled) {
                $session = $candidate;
                break;
            }
        }

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $client->request('POST', '/api/sessions/' . $session->getId() . '/enroll', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['scheduledExamIds' => []]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertEmailCount(1);

        $email = $this->getMailerMessage();
        $this->assertEmailAddressContains($email, 'To', UserFixtures::ADMIN_EMAIL);
        $this->assertEmailHeaderSame($email, 'Subject', sprintf('Inscription enregistrée — %s · GetTested', trim($session->getAssessment()->getLabel() . ' ' . $session->getLevel()?->getLabel())));
        $this->assertEmailHtmlBodyContains($email, '/dashboard/mes-inscriptions/');
    }

    public function testSessionCancellationNotifiesEnrolledCandidates(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $session = null;
        foreach ($em->getRepository(Session::class)->findBy(['status' => SessionStatusEnum::OPEN]) as $candidate) {
            if (!$candidate->getEnrollments()->isEmpty()) {
                $session = $candidate;
                break;
            }
        }
        $this->assertNotNull($session, 'Les fixtures doivent contenir une session OPEN avec des inscrits.');
        $enrolledCount = $session->getEnrollments()->count();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $client->request('PATCH', '/api/sessions/' . $session->getId() . '/transition', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['transition' => 'cancel_from_open']));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertEmailCount($enrolledCount);
        $this->assertEmailHtmlBodyContains($this->getMailerMessage(), 'a annulé la session');
    }
}
