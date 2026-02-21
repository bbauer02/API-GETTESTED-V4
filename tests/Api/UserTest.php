<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class UserTest extends WebTestCase
{
    use ApiTestTrait;

    public function testGetMeWithJwt(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('GET', '/api/users/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(UserFixtures::USER1_EMAIL, $data['email']);
        $this->assertArrayHasKey('firstname', $data);
        $this->assertArrayHasKey('lastname', $data);
    }

    public function testGetMeWithoutJwt(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/users/me', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testPatchMe(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER1_EMAIL]);
        $client->loginUser($user);

        $client->request('PATCH', '/api/users/me', [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'phone' => '0612345678',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('0612345678', $data['phone']);
    }

    public function testGetUsersAsAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('GET', '/api/users', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testGetUsersAsUser(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('GET', '/api/users', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminPatchUserIsActive(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $user1 = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER1_EMAIL]);

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('PATCH', '/api/users/' . $user1->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'isActive' => false,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Vérifier via la DB (le sérialiseur Symfony expose 'active' et non 'isActive')
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $updatedUser = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER1_EMAIL]);
        $this->assertFalse($updatedUser->isActive());
    }

    public function testAdminSoftDeleteUser(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $user1 = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER1_EMAIL]);
        $userId = (string) $user1->getId();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('DELETE', '/api/users/' . $userId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // User should no longer appear in list (soft delete filter active)
        $client->request('GET', '/api/users', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $data = json_decode($client->getResponse()->getContent(), true);
        $emails = array_column($data, 'email');
        $this->assertNotContains(UserFixtures::USER1_EMAIL, $emails);
    }
}
