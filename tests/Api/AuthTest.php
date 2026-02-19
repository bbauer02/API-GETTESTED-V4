<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthTest extends WebTestCase
{
    use ApiTestTrait;

    public function testRegisterValid(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'email' => 'newuser@test.com',
            'password' => 'password123',
            'firstname' => 'Nouveau',
            'lastname' => 'Utilisateur',
            'civility' => 'M',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('newuser@test.com', $data['email']);
    }

    public function testRegisterDuplicateEmail(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'email' => UserFixtures::USER1_EMAIL,
            'password' => 'password123',
            'firstname' => 'Duplicate',
            'lastname' => 'User',
            'civility' => 'M',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testRegisterMissingFields(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'email' => 'incomplete@test.com',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testLoginValid(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => UserFixtures::USER1_EMAIL,
            'password' => UserFixtures::DEFAULT_PASSWORD,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
    }

    public function testLoginInvalidCredentials(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => UserFixtures::USER1_EMAIL,
            'password' => 'wrongpassword',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testLoginInactiveAccount(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => UserFixtures::INACTIVE_EMAIL,
            'password' => UserFixtures::DEFAULT_PASSWORD,
        ]));

        // UserChecker blocks pre-auth, returns 401 with "Compte désactivé." message
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('désactivé', $data['message'] ?? '');
    }

    public function testVerifyEmailValidToken(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $tokenService = $container->get(TokenService::class);
        $em = $container->get(EntityManagerInterface::class);

        $user = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]);
        $this->assertNotNull($user);
        $this->assertFalse($user->isVerified());

        $token = $tokenService->generateVerificationToken($user);

        $client->request('POST', '/api/auth/verify-email/' . $token, [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $em->refresh($user);
        $this->assertTrue($user->isVerified());
    }

    public function testVerifyEmailInvalidToken(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/verify-email/invalid-token', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
