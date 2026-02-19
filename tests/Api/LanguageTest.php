<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class LanguageTest extends WebTestCase
{
    use ApiTestTrait;

    public function testGetLanguagesWithoutAuth(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $client->request('GET', '/api/languages', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data);
    }

    public function testPostLanguageAsUser(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/languages', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'code' => 'xx',
            'nameOriginal' => 'Testlang',
            'nameEn' => 'Testlang',
            'nameFr' => 'Testlang',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testPostLanguageAsAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/languages', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'code' => 'xx',
            'nameOriginal' => 'Testlang',
            'nameEn' => 'Testlang',
            'nameFr' => 'Testlang',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }
}
