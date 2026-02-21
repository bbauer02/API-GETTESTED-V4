<?php

namespace App\Tests\Api;

use App\DataFixtures\AssessmentFixtures;
use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Assessment;
use App\Entity\AssessmentOwnership;
use App\Entity\Institute;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AssessmentTest extends WebTestCase
{
    use ApiTestTrait;

    public function testGetAssessmentsPublic(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $client->request('GET', '/api/assessments', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertGreaterThanOrEqual(3, count($data));
    }

    public function testPostAssessmentAsPlatformAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Baptiste est PLATFORM_ADMIN → peut créer un assessment interne
        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/assessments', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'DELF',
            'ref' => 'DELF',
            'isInternal' => true,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('DELF', $data['label']);

        // Vérifier isInternal via la DB (la clé JSON peut être 'isInternal' ou 'internal')
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $created = $em->getRepository(Assessment::class)->find($data['id']);
        $this->assertTrue($created->isInternal());
    }

    public function testPostAssessmentViaInstituteAsInstituteAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est INSTITUTE_ADMIN de Institut Français
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/assessments', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'Test Institut Perso',
            'ref' => 'TIP-001',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Test Institut Perso', $data['label']);

        // Vérifier isInternal = false (forcé par le processor)
        $assessment = $em->getRepository(Assessment::class)->find($data['id']);
        $this->assertFalse($assessment->isInternal());

        // Vérifier que l'ownership OWNER a été créée
        $ownership = $em->getRepository(AssessmentOwnership::class)->findOneBy([
            'assessment' => $assessment,
            'institute' => $institute,
        ]);
        $this->assertNotNull($ownership);
        $this->assertEquals('OWNER', $ownership->getOwnershipType()->value);
    }

    public function testPatchAssessmentAsOwnerInstituteAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est INSTITUTE_ADMIN de Institut Français, qui est OWNER du test personnalisé
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $assessment = $em->getRepository(Assessment::class)->findOneBy(['label' => AssessmentFixtures::CUSTOM_LABEL]);

        $client->request('PATCH', '/api/assessments/' . $assessment->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'Test personnalisé modifié',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Test personnalisé modifié', $data['label']);
    }

    public function testPatchAssessmentAsBuyerDenied(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe est CUSTOMER de Tenri (institute 2), qui est BUYER du test personnalisé
        // Mais CUSTOMER ≠ INSTITUTE_ADMIN → ne peut pas modifier
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $user2 = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]);
        $client->loginUser($user2);

        $assessment = $em->getRepository(Assessment::class)->findOneBy(['label' => AssessmentFixtures::CUSTOM_LABEL]);

        $client->request('PATCH', '/api/assessments/' . $assessment->getId(), [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'Tentative modification buyer',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteAssessmentAsPlatformAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $assessment = $em->getRepository(Assessment::class)->findOneBy(['label' => AssessmentFixtures::JLPT_LABEL]);

        $client->request('DELETE', '/api/assessments/' . $assessment->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testDeleteAssessmentAsInstituteAdminDenied(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est INSTITUTE_ADMIN mais pas PLATFORM_ADMIN → ne peut pas supprimer
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $assessment = $em->getRepository(Assessment::class)->findOneBy(['label' => AssessmentFixtures::CUSTOM_LABEL]);

        $client->request('DELETE', '/api/assessments/' . $assessment->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
