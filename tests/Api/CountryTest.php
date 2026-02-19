<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CountryTest extends WebTestCase
{
    use ApiTestTrait;

    public function testGetCountriesWithoutAuth(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $client->request('GET', '/api/countries', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data);
    }

    public function testGetCountryDetail(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $client->request('GET', '/api/countries/FR', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('FR', $data['code']);
        $this->assertArrayHasKey('spokenLanguages', $data);
    }

    public function testPostCountryAsUser(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/countries', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'code' => 'XX',
            'alpha3' => 'XXX',
            'nameOriginal' => 'Test',
            'nameEn' => 'Test',
            'nameFr' => 'Test',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testPostCountryAsAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/countries', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'code' => 'XX',
            'alpha3' => 'XXX',
            'nameOriginal' => 'Testland',
            'nameEn' => 'Testland',
            'nameFr' => 'Testland',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testFilterByNameFr(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $client->request('GET', '/api/countries?nameFr=Fran', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data);
        foreach ($data as $country) {
            $this->assertStringContainsStringIgnoringCase('Fran', $country['nameFr']);
        }
    }
}
