<?php

namespace App\Tests\Api;

use App\DataFixtures\AssessmentFixtures;
use App\DataFixtures\ExamFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Assessment;
use App\Entity\Exam;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ExamTest extends WebTestCase
{
    use ApiTestTrait;

    public function testGetExamsPublic(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $client->request('GET', '/api/exams', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    public function testPostExamUnderAssessmentAsOwner(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est INSTITUTE_ADMIN de Institut Français, OWNER du test personnalisé
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $assessment = $em->getRepository(Assessment::class)->findOneBy(['label' => AssessmentFixtures::CUSTOM_LABEL]);

        $client->request('POST', '/api/assessments/' . $assessment->getId() . '/exams', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'Exam Custom Writing',
            'isWritten' => true,
            'isOption' => false,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Exam Custom Writing', $data['label']);
    }

    public function testPatchExamAsOwnerInstituteAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est INSTITUTE_ADMIN de Institut Français, OWNER du test personnalisé
        // On crée d'abord un exam sous le test personnalisé via l'API, puis on le modifie
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $assessment = $em->getRepository(Assessment::class)->findOneBy(['label' => AssessmentFixtures::CUSTOM_LABEL]);

        // Créer un exam
        $client->request('POST', '/api/assessments/' . $assessment->getId() . '/exams', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'Exam à modifier',
            'isWritten' => false,
            'isOption' => false,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $examData = json_decode($client->getResponse()->getContent(), true);

        // Modifier l'exam
        $client->request('PATCH', '/api/exams/' . $examData['id'], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'Exam modifié',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Exam modifié', $data['label']);
    }

    public function testPostExamUnderAssessmentAsBuyerDenied(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe est CUSTOMER de Tenri (BUYER du test personnalisé), pas ADMIN
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $user2 = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]);
        $client->loginUser($user2);

        $assessment = $em->getRepository(Assessment::class)->findOneBy(['label' => AssessmentFixtures::CUSTOM_LABEL]);

        $client->request('POST', '/api/assessments/' . $assessment->getId() . '/exams', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'Tentative exam buyer',
            'isWritten' => false,
            'isOption' => false,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testGetExamsUnderAssessment(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $assessment = $em->getRepository(Assessment::class)->findOneBy(['label' => AssessmentFixtures::TOEIC_LABEL]);

        $client->request('GET', '/api/assessments/' . $assessment->getId() . '/exams', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(2, $data);
    }
}
