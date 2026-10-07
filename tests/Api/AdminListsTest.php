<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Listes du back-office : pagination réglable, recherche multi-champs, tri et indicateurs.
 */
class AdminListsTest extends WebTestCase
{
    use ApiTestTrait;

    private function getJson($client, string $url, string $token): array
    {
        $client->request('GET', $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        return json_decode($client->getResponse()->getContent(), true);
    }

    public function testUsersSearchSortAndPagination(): void
    {
        $client = static::createClient();
        $this->loadFixtures();
        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        // Recherche « q » sur prénom, nom ou email (insensible à la casse)
        $data = $this->getJson($client, '/api/users?q=LEFEB', $token);
        $this->assertSame(1, $data['totalItems']);
        $this->assertSame(UserFixtures::USER2_EMAIL, $data['member'][0]['email']);

        $data = $this->getJson($client, '/api/users?q=yahoo.co.jp', $token);
        $this->assertSame(UserFixtures::USER1_EMAIL, $data['member'][0]['email']);

        // Pagination réglable par le client et tri
        $data = $this->getJson($client, '/api/users?itemsPerPage=2&page=1&order[lastname]=asc', $token);
        $this->assertCount(2, $data['member']);
        $this->assertGreaterThan(2, $data['totalItems']);
        $this->assertLessThanOrEqual(0, strcasecmp($data['member'][0]['lastname'], $data['member'][1]['lastname']));
    }

    public function testAdminStatsAreComputedOnTheWholeDatabase(): void
    {
        $client = static::createClient();
        $this->loadFixtures();
        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('GET', '/api/admin/stats', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $stats = json_decode($client->getResponse()->getContent(), true);

        $users = $this->getJson($client, '/api/users?itemsPerPage=1', $token);
        $this->assertSame($users['totalItems'], $stats['users']);
        $this->assertArrayHasKey('sessionsByStatus', $stats);
        $this->assertArrayHasKey('invoicesByStatus', $stats);
    }

    public function testAdminStatsForbiddenForNonAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('GET', '/api/admin/stats', [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
