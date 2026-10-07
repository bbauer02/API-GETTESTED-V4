<?php

namespace App\Tests\Api;

use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Institute;
use App\Entity\InstituteMembership;
use App\Entity\User;
use App\Enum\MembershipStatusEnum;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InvitationTest extends WebTestCase
{
    use ApiTestTrait;

    private const NEW_EMAIL = 'nouveau.membre@example.com';

    public function testInviteNewUserCreatesPendingMembershipAndSendsEmail(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/memberships/invite', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'email' => self::NEW_EMAIL,
            'firstname' => 'Nouveau',
            'lastname' => 'Membre',
            'role' => 'TEACHER',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('TEACHER', $data['role']);
        $this->assertEquals('PENDING', $data['status']);
        $this->assertEquals(self::NEW_EMAIL, $data['user']['email']);
        $this->assertFalse($data['user']['isActive']);
        $this->assertFalse($data['user']['isVerified']);
        $this->assertArrayHasKey('phone', $data['user']);

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHtmlBodyContains($email, '/auth/jwt/set-password/?token=');
    }

    public function testInviteExistingActiveUserCreatesActiveMembership(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        // Christophe existe et est actif, pas encore membre de Institut Français
        $client->request('POST', '/api/institutes/' . $institute->getId() . '/memberships/invite', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'email' => UserFixtures::USER2_EMAIL,
            'firstname' => 'Christophe',
            'lastname' => 'Lefebre',
            'role' => 'STAFF',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('ACTIVE', $data['status']);
        $this->assertEmailCount(0);

        // Doublon → 409
        $client->request('POST', '/api/institutes/' . $institute->getId() . '/memberships/invite', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'email' => UserFixtures::USER2_EMAIL,
            'firstname' => 'Christophe',
            'lastname' => 'Lefebre',
            'role' => 'STAFF',
        ]));
        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testInviteAsNonAdminForbidden(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe est CUSTOMER de Tenri
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE2_LABEL]);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/memberships/invite', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'email' => self::NEW_EMAIL,
            'firstname' => 'X',
            'lastname' => 'Y',
            'role' => 'STAFF',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAcceptInvitationActivatesAccountAndMembership(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/memberships/invite', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'email' => self::NEW_EMAIL,
            'firstname' => 'Nouveau',
            'lastname' => 'Membre',
            'role' => 'ADMIN',
        ]));
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Le compte est inactif : login refusé
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => self::NEW_EMAIL,
            'password' => 'whatever123',
        ]));
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $user = $em->getRepository(User::class)->findOneBy(['email' => self::NEW_EMAIL]);
        $invitationToken = $container->get(TokenService::class)->generateInvitationToken($user);

        // GET invitation → pré-remplissage
        $client->request('GET', '/api/auth/invitation/' . $invitationToken, [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $info = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(self::NEW_EMAIL, $info['email']);
        $this->assertEquals('Nouveau', $info['firstname']);
        $this->assertEquals(InstituteFixtures::INSTITUTE1_LABEL, $info['institute']);

        // Mot de passe trop court → 422
        $client->request('POST', '/api/auth/accept-invitation/' . $invitationToken, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'password' => 'short',
        ]));
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Token invalide → 400
        $client->request('POST', '/api/auth/accept-invitation/not-a-token', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'password' => 'NewPassword123',
        ]));
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        // Acceptation
        $client->request('POST', '/api/auth/accept-invitation/' . $invitationToken, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'password' => 'NewPassword123',
        ]));
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $accepted = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(self::NEW_EMAIL, $accepted['email']);

        $em->clear();
        $user = $em->getRepository(User::class)->findOneBy(['email' => self::NEW_EMAIL]);
        $this->assertTrue($user->isActive());
        $this->assertTrue($user->isVerified());
        $this->assertNotNull($user->getEmailVerifiedAt());

        $membership = $em->getRepository(InstituteMembership::class)->findOneBy(['user' => $user, 'institute' => $institute]);
        $this->assertEquals(MembershipStatusEnum::ACTIVE, $membership->getStatus());

        // Login OK avec le nouveau mot de passe
        $newToken = $this->getJwtToken(self::NEW_EMAIL, 'NewPassword123');
        $this->assertNotEmpty($newToken);

        // Le nouvel ADMIN peut lister les membres avec leur status
        $client->request('GET', '/api/institutes/' . $institute->getId() . '/memberships', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $newToken,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $members = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(3, $members);
        foreach ($members as $member) {
            $this->assertArrayHasKey('status', $member);
            $this->assertArrayHasKey('isActive', $member['user']);
        }
    }

    public function testArchiveReactivateAndDeleteMembership(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);
        $admin = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::ADMIN_EMAIL]);
        $membership = $em->getRepository(InstituteMembership::class)->findOneBy(['user' => $admin, 'institute' => $institute]);

        $client->request('PATCH', '/api/institute-memberships/' . $membership->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['status' => 'ARCHIVED']));
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertEquals('ARCHIVED', json_decode($client->getResponse()->getContent(), true)['status']);

        $client->request('PATCH', '/api/institute-memberships/' . $membership->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['status' => 'ACTIVE', 'role' => 'STAFF']));
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('ACTIVE', $data['status']);
        $this->assertEquals('STAFF', $data['role']);

        $client->request('DELETE', '/api/institute-memberships/' . $membership->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testArchivedAdminLosesRights(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);
        $ayaka = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER1_EMAIL]);
        $membership = $em->getRepository(InstituteMembership::class)->findOneBy(['user' => $ayaka, 'institute' => $institute]);
        $membership->setStatus(MembershipStatusEnum::ARCHIVED);
        $em->flush();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/memberships/invite', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'email' => self::NEW_EMAIL,
            'firstname' => 'X',
            'lastname' => 'Y',
            'role' => 'STAFF',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
