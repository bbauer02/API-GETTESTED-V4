<?php

namespace App\Tests\Api;

use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Institute;
use App\Entity\InstituteMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InstituteTest extends WebTestCase
{
    use ApiTestTrait;

    public function testGetInstitutesPublic(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $client->request('GET', '/api/institutes', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    public function testCreateInstituteAsAuthUser(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka (user vérifié) crée un nouvel institut
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/institutes', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'Nouvel Institut Test',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Nouvel Institut Test', $data['label']);

        // Vérifier que le membership ADMIN a été créé automatiquement
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->find($data['id']);
        $user = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER1_EMAIL]);

        $membership = $em->getRepository(InstituteMembership::class)->findOneBy([
            'institute' => $institute,
            'user' => $user,
        ]);

        $this->assertNotNull($membership);
        $this->assertEquals('ADMIN', $membership->getRole()->value);
    }

    public function testCreateInstituteWithoutAuth(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/institutes', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'Institut Sans Auth',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testPatchInstituteAsInstituteAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est INSTITUTE_ADMIN de Institut Français (institut 1)
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('PATCH', '/api/institutes/' . $institute->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'Institut Français Modifié',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Institut Français Modifié', $data['label']);
    }

    public function testPatchInstituteAsNonMember(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe (user2) n'est pas membre de Institut Français (institut 1)
        // Il n'est que CUSTOMER de Tenri (institut 2)
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $user2 = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]);
        $client->loginUser($user2);

        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('PATCH', '/api/institutes/' . $institute->getId(), [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'Tentative modification',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteInstituteAsPlatformAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Baptiste est PLATFORM_ADMIN → peut supprimer n'importe quel institut
        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE2_LABEL]);

        $client->request('DELETE', '/api/institutes/' . $institute->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testDeleteInstituteAsInstituteAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est INSTITUTE_ADMIN de Institut Français mais PAS PLATFORM_ADMIN
        // → ne peut pas supprimer
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('DELETE', '/api/institutes/' . $institute->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
