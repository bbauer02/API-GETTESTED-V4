<?php

namespace App\Tests\Api;

use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Institute;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InstituteMembershipTest extends WebTestCase
{
    use ApiTestTrait;

    public function testInviteMemberAsInstituteAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est INSTITUTE_ADMIN de Institut Français (institut 1)
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);
        // Christophe (user2) n'est pas encore membre de Institut Français
        $user2 = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/memberships', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'user' => '/api/users/' . $user2->getId(),
            'role' => 'TEACHER',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('TEACHER', $data['role']);
    }

    public function testInviteMemberAsNonAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe (user2) est CUSTOMER de Tenri → pas ADMIN → ne peut pas inviter
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $user2 = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]);
        $client->loginUser($user2);

        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE2_LABEL]);
        $user1 = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER1_EMAIL]);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/memberships', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'user' => '/api/users/' . $user1->getId(),
            'role' => 'STAFF',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testInviteDuplicateMember(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est INSTITUTE_ADMIN de Institut Français
        // Baptiste (admin) est déjà CUSTOMER de Institut Français → doublon
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);
        $admin = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::ADMIN_EMAIL]);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/memberships', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'user' => '/api/users/' . $admin->getId(),
            'role' => 'TEACHER',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGetMembershipsAsInstituteAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka est INSTITUTE_ADMIN de Institut Français
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('GET', '/api/institutes/' . $institute->getId() . '/memberships', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        // Institut Français a 2 membres : Baptiste (CUSTOMER) + Ayaka (ADMIN)
        $this->assertCount(2, $data);
    }

    public function testGetMembershipsAsNonAdminMember(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe (user2) est CUSTOMER de Tenri → membre non-ADMIN → doit pouvoir voir
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE2_LABEL]);

        $client->request('GET', '/api/institutes/' . $institute->getId() . '/memberships', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        // Tenri a 3 membres : Baptiste (CUSTOMER), Christophe (CUSTOMER), Didier (TEACHER)
        $this->assertCount(3, $data);
    }

    public function testGetMembershipsAsNonMemberDenied(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe (user2) n'est PAS membre de Institut Français → doit être refusé
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('GET', '/api/institutes/' . $institute->getId() . '/memberships', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
