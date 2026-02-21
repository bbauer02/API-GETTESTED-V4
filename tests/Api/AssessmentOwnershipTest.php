<?php

namespace App\Tests\Api;

use App\DataFixtures\AssessmentFixtures;
use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Assessment;
use App\Entity\Institute;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AssessmentOwnershipTest extends WebTestCase
{
    use ApiTestTrait;

    public function testGetOwnershipsAsMember(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est membre (ADMIN) de Institut Français → peut voir les ownerships
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('GET', '/api/institutes/' . $institute->getId() . '/ownerships', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        // Institut Français a 1 ownership (OWNER du test personnalisé)
        $this->assertCount(1, $data);
    }

    public function testGetOwnershipsAsNonMemberDenied(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe (user2) n'est PAS membre de Institut Français → refusé
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('GET', '/api/institutes/' . $institute->getId() . '/ownerships', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testPostBuyerOwnershipAsPlatformAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Baptiste est PLATFORM_ADMIN → peut accorder un accès BUYER
        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE2_LABEL]);
        $assessment = $em->getRepository(Assessment::class)->findOneBy(['label' => AssessmentFixtures::TOEIC_LABEL]);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/ownerships', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'assessment' => '/api/assessments/' . $assessment->getId(),
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('BUYER', $data['ownershipType']);
    }
}
