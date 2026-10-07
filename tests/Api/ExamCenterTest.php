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

class ExamCenterTest extends WebTestCase
{
    use ApiTestTrait;

    private function createExamCenter(string $token, Institute $institute, array $overrides = []): array
    {
        $client = static::getClient();
        $client->request('POST', '/api/institutes/' . $institute->getId() . '/exam-centers', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($overrides + [
            'label' => 'Salle Rimbaud',
            'isDefault' => true,
            'address' => [
                'address1' => '12 rue des Examens',
                'zipcode' => '75010',
                'city' => 'Paris',
                'countryCode' => 'FR',
            ],
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return json_decode($client->getResponse()->getContent(), true);
    }

    public function testExamCenterCrud(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $center = $this->createExamCenter($token, $institute);
        $this->assertEquals('Salle Rimbaud', $center['label']);
        $this->assertTrue($center['isDefault']);
        $this->assertEquals('12 rue des Examens', $center['address']['address1']);

        // Un second centre par défaut retire le flag du premier
        $second = $this->createExamCenter($token, $institute, ['label' => 'Salle Verlaine']);
        $this->assertTrue($second['isDefault']);

        $client->request('GET', '/api/institutes/' . $institute->getId() . '/exam-centers', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $list = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(2, $list);
        $defaults = array_filter($list, static fn ($c) => $c['isDefault']);
        $this->assertCount(1, $defaults);

        $client->request('PATCH', '/api/exam-centers/' . $center['id'], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['label' => 'Salle Rimbaud (rez-de-chaussée)']));
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertEquals('Salle Rimbaud (rez-de-chaussée)', json_decode($client->getResponse()->getContent(), true)['label']);

        $client->request('GET', '/api/exam-centers/' . $center['id'], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $client->request('DELETE', '/api/exam-centers/' . $center['id'], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testExamCenterForbiddenForNonMember(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe n'est pas membre de Institut Français
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/exam-centers', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['label' => 'X', 'address' => ['city' => 'Paris']]));
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request('GET', '/api/institutes/' . $institute->getId() . '/exam-centers', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testScheduledExamCopiesExamCenterAddressAndCanBeDeleted(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);
        $center = $this->createExamCenter($token, $institute);

        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::DRAFT]);
        $exam = $em->getRepository(Exam::class)->findOneBy(['assessment' => $session->getAssessment()]);

        $client->request('POST', '/api/sessions/' . $session->getId() . '/scheduled-exams', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'startDate' => (clone $session->getStart())->modify('+2 hours')->format(\DateTimeInterface::ATOM),
            'exam' => '/api/exams/' . $exam->getId(),
            'examCenter' => '/api/exam-centers/' . $center['id'],
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $scheduled = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('12 rue des Examens', $scheduled['address']['address1']);
        $this->assertEquals('75010', $scheduled['address']['zipcode']);
        $this->assertEquals('Salle Rimbaud', $scheduled['room']);
        $this->assertEquals($center['id'], $scheduled['examCenter']['id']);
        $this->assertEquals('Salle Rimbaud', $scheduled['examCenter']['label']);
        $this->assertArrayHasKey('address', $scheduled['examCenter']);

        // Sans examCenter ni adresse → adresse de l'institut
        $client->request('POST', '/api/sessions/' . $session->getId() . '/scheduled-exams', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'startDate' => (clone $session->getStart())->modify('+3 hours')->format(\DateTimeInterface::ATOM),
            'exam' => '/api/exams/' . $exam->getId(),
        ]));
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $fallback = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals($institute->getAddress()->getCity(), $fallback['address']['city']);

        $client->request('DELETE', '/api/scheduled-exams/' . $scheduled['id'], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testDeleteScheduledExamWithEnrollmentsConflicts(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // Session TOEIC OPEN : Christophe et Didier y sont inscrits
        $sessions = array_filter(
            $em->getRepository(Session::class)->findBy(['status' => SessionStatusEnum::OPEN]),
            fn (Session $s) => !$s->getEnrollments()->isEmpty()
        );
        $session = reset($sessions);
        $scheduledExam = $session->getScheduledExams()->first();

        $client->request('DELETE', '/api/scheduled-exams/' . $scheduledExam->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('inscription', $data['detail']);
    }
}
