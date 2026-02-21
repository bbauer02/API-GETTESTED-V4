<?php

namespace App\Tests\Api;

use App\DataFixtures\ExamFixtures;
use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Assessment;
use App\Entity\Exam;
use App\Entity\Institute;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InstituteExamPricingTest extends WebTestCase
{
    use ApiTestTrait;

    public function testGetExamPricingsAsMember(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est membre (ADMIN) de Institut Français → peut voir les tarifs
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('GET', '/api/institutes/' . $institute->getId() . '/exam-pricings', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
    }

    public function testPostExamPricingAsInstituteAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est INSTITUTE_ADMIN de Institut Français, qui est OWNER du test personnalisé
        // On crée d'abord un exam sous ce test, puis on définit un tarif
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        // Créer un exam sous le test personnalisé
        $assessment = $em->getRepository(Assessment::class)->findOneBy(['ref' => 'CUSTOM-IF']);

        $client->request('POST', '/api/assessments/' . $assessment->getId() . '/exams', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'Exam pour pricing',
            'isWritten' => false,
            'isOption' => false,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $examData = json_decode($client->getResponse()->getContent(), true);

        // Créer un tarif pour cet exam
        $client->request('POST', '/api/institutes/' . $institute->getId() . '/exam-pricings', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'exam' => '/api/exams/' . $examData['id'],
            'price' => [
                'amount' => 75.0,
                'currency' => 'EUR',
                'tva' => 20.0,
            ],
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testPostExamPricingForInaccessibleExamDenied(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est INSTITUTE_ADMIN de Institut Français
        // Mais le TOEIC est un test interne sans ownership pour Institut Français → pas d'accès
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);
        $exam = $em->getRepository(Exam::class)->findOneBy(['label' => ExamFixtures::TOEIC_LISTENING_LABEL]);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/exam-pricings', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'exam' => '/api/exams/' . $exam->getId(),
            'price' => [
                'amount' => 100.0,
                'currency' => 'EUR',
                'tva' => 20.0,
            ],
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
