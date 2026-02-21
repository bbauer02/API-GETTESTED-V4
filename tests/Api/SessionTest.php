<?php

namespace App\Tests\Api;

use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SessionTest extends WebTestCase
{
    use ApiTestTrait;

    public function testGetSessionContainsScheduledExams(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy([]);

        $client->request('GET', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);

        // scheduledExams doit être un tableau avec au moins un élément embarqué
        $this->assertArrayHasKey('scheduledExams', $data);
        $this->assertNotEmpty($data['scheduledExams']);

        $scheduled = $data['scheduledExams'][0];
        $this->assertArrayHasKey('id', $scheduled);
        $this->assertArrayHasKey('startDate', $scheduled);
        $this->assertArrayHasKey('room', $scheduled);
        $this->assertEquals('Salle A', $scheduled['room']);

        // L'exam doit être embarqué avec ses détails
        $this->assertArrayHasKey('exam', $scheduled);
        $this->assertIsArray($scheduled['exam']);
        $this->assertArrayHasKey('id', $scheduled['exam']);
        $this->assertArrayHasKey('label', $scheduled['exam']);
        $this->assertEquals('TOEIC Listening', $scheduled['exam']['label']);

        // L'adresse doit être embarquée
        $this->assertArrayHasKey('address', $scheduled);
        $this->assertIsArray($scheduled['address']);
        $this->assertArrayHasKey('address1', $scheduled['address']);
        $this->assertEquals('15 rue de Tokyo', $scheduled['address']['address1']);
    }

    public function testGetSessionContainsEnrollments(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy([]);

        $client->request('GET', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);

        // enrollments doit être un tableau avec au moins un élément embarqué
        $this->assertArrayHasKey('enrollments', $data);
        $this->assertNotEmpty($data['enrollments']);

        $enrollment = $data['enrollments'][0];
        $this->assertArrayHasKey('id', $enrollment);
        $this->assertArrayHasKey('registrationDate', $enrollment);

        // Le user doit être embarqué avec ses détails
        $this->assertArrayHasKey('user', $enrollment);
        $this->assertIsArray($enrollment['user']);
        $this->assertArrayHasKey('id', $enrollment['user']);
        $this->assertArrayHasKey('firstname', $enrollment['user']);
        $this->assertArrayHasKey('lastname', $enrollment['user']);
        $this->assertArrayHasKey('email', $enrollment['user']);
        $this->assertEquals('Christophe', $enrollment['user']['firstname']);
        $this->assertEquals('cLefebre@gmail.com', $enrollment['user']['email']);
    }

    public function testGetSessionContainsExamPricings(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy([]);

        $client->request('GET', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);

        $scheduled = $data['scheduledExams'][0];

        // examPricing doit être embarqué avec les détails du prix
        $this->assertArrayHasKey('examPricing', $scheduled);
        $this->assertIsArray($scheduled['examPricing']);
        $this->assertArrayHasKey('id', $scheduled['examPricing']);
        $this->assertArrayHasKey('price', $scheduled['examPricing']);
        $this->assertArrayHasKey('active', $scheduled['examPricing']);
        $this->assertTrue($scheduled['examPricing']['active']);

        // Détails du prix
        $price = $scheduled['examPricing']['price'];
        $this->assertIsArray($price);
        $this->assertEquals(65.0, $price['amount']);
        $this->assertEquals('EUR', $price['currency']);
        $this->assertEquals(20.0, $price['tva']);
    }

    public function testGetSessionContainsInstitute(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy([]);

        $client->request('GET', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);

        // Institute doit être embarqué
        $this->assertArrayHasKey('institute', $data);
        $this->assertIsArray($data['institute']);
        $this->assertArrayHasKey('id', $data['institute']);
        $this->assertArrayHasKey('label', $data['institute']);
        $this->assertEquals('Institut Français', $data['institute']['label']);

        // Assessment doit être embarqué
        $this->assertArrayHasKey('assessment', $data);
        $this->assertIsArray($data['assessment']);
        $this->assertArrayHasKey('label', $data['assessment']);
        $this->assertEquals('TOEIC', $data['assessment']['label']);
    }
}
