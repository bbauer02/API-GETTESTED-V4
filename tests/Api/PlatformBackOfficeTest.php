<?php

namespace App\Tests\Api;

use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Institute;
use App\Entity\InstituteMembership;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\InstituteStatusEnum;
use App\Enum\PlatformRoleEnum;
use App\Enum\SessionStatusEnum;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back-office plateforme : validation des instituts et invitation d'utilisateurs par l'admin.
 */
class PlatformBackOfficeTest extends WebTestCase
{
    use ApiTestTrait;

    private const INVITED_EMAIL = 'invite.plateforme@example.com';

    private function jsonHeaders(string $token, string $contentType = 'application/json'): array
    {
        return [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => $contentType,
            'HTTP_ACCEPT' => 'application/json',
        ];
    }

    // ========================
    // Validation des instituts
    // ========================

    public function testInstituteCreatedByUserIsPendingReview(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/institutes', [], [], $this->jsonHeaders($token), json_encode([
            'label' => 'Institut En Attente',
            // Le statut n'est pas modifiable à la création
            'status' => 'ACTIVE',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('PENDING_REVIEW', $data['status']);

        // Le créateur devient administrateur de l'institut
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]);
        $membership = $em->getRepository(InstituteMembership::class)->findOneBy([
            'institute' => $data['id'],
            'user' => $user,
        ]);
        $this->assertNotNull($membership);
        $this->assertSame('ADMIN', $membership->getRole()->value);
    }

    public function testInstituteCreatedByPlatformAdminIsActiveWithoutMembership(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/institutes', [], [], $this->jsonHeaders($token), json_encode([
            'label' => 'Institut Créé Par La Plateforme',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('ACTIVE', $data['status']);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $memberships = $em->getRepository(InstituteMembership::class)->findBy(['institute' => $data['id']]);
        $this->assertCount(0, $memberships, "L'admin plateforme ne doit pas devenir membre de l'institut créé.");

        // L'admin plateforme désigne l'administrateur de l'institut par invitation
        $client->request('POST', '/api/institutes/' . $data['id'] . '/memberships/invite', [], [], $this->jsonHeaders($token), json_encode([
            'email' => 'directeur.nouvel.institut@example.com',
            'firstname' => 'Jeanne',
            'lastname' => 'Directrice',
            'role' => 'ADMIN',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $membership = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('ADMIN', $membership['role']);
        $this->assertSame('PENDING', $membership['status']);
        $this->assertEmailCount(1);
    }

    public function testOpenSessionRefusedForNonValidatedInstitute(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $session = $em->getRepository(Session::class)->findOneBy(['status' => SessionStatusEnum::DRAFT]);
        $session->getInstitute()->setStatus(InstituteStatusEnum::PENDING_REVIEW);
        $em->flush();

        $client->request('PATCH', '/api/sessions/' . $session->getId() . '/transition', [], [],
            $this->jsonHeaders($token, 'application/merge-patch+json'),
            json_encode(['transition' => 'open'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertSame(
            'L\'institut doit être validé par la plateforme.',
            json_decode($client->getResponse()->getContent(), true)['detail']
        );
    }

    public function testPlatformAdminValidatesInstitute(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);
        $institute->setStatus(InstituteStatusEnum::PENDING_REVIEW);
        $em->flush();
        $instituteId = (string) $institute->getId();

        // Filtre exact sur le statut
        $adminToken = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $client->request('GET', '/api/institutes?status=PENDING_REVIEW', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseIsSuccessful();
        $list = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame([$instituteId], array_column($list, 'id'));

        // Un administrateur d'institut ne peut pas valider son propre institut
        $userToken = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $client->request('PATCH', '/api/institutes/' . $instituteId . '/status', [], [],
            $this->jsonHeaders($userToken, 'application/merge-patch+json'),
            json_encode(['status' => 'ACTIVE'])
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // Le statut n'est pas modifiable via le PATCH classique de l'institut
        $client->request('PATCH', '/api/institutes/' . $instituteId, [], [],
            $this->jsonHeaders($userToken, 'application/merge-patch+json'),
            json_encode(['status' => 'ACTIVE'])
        );
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('PENDING_REVIEW', $data['status']);

        // L'admin plateforme valide : l'admin de l'institut est prévenu par email
        $client->request('PATCH', '/api/institutes/' . $instituteId . '/status', [], [],
            $this->jsonHeaders($adminToken, 'application/merge-patch+json'),
            json_encode(['status' => 'ACTIVE'])
        );
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('ACTIVE', $data['status']);

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailAddressContains($email, 'To', strtolower(UserFixtures::USER1_EMAIL));
        $this->assertEmailHtmlBodyContains($email, 'validé');

        // Suspension : pas d'email
        $client->request('PATCH', '/api/institutes/' . $instituteId . '/status', [], [],
            $this->jsonHeaders($adminToken, 'application/merge-patch+json'),
            json_encode(['status' => 'SUSPENDED'])
        );
        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(0);
        $this->assertSame('SUSPENDED', json_decode($client->getResponse()->getContent(), true)['status']);

        // Statut inconnu refusé
        $client->request('PATCH', '/api/institutes/' . $instituteId . '/status', [], [],
            $this->jsonHeaders($adminToken, 'application/merge-patch+json'),
            json_encode(['status' => 'N_IMPORTE_QUOI'])
        );
        $this->assertGreaterThanOrEqual(400, $client->getResponse()->getStatusCode());
        $this->assertLessThan(500, $client->getResponse()->getStatusCode());
    }

    // ========================
    // Invitation d'utilisateurs par l'admin plateforme
    // ========================

    public function testPlatformAdminInvitesUser(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/admin/users/invite', [], [], $this->jsonHeaders($token), json_encode([
            'email' => self::INVITED_EMAIL,
            'firstname' => 'Inès',
            'lastname' => 'Invitée',
            'platformRole' => 'ADMIN',
            'phone' => '612345678',
            'phoneCountryCode' => '+33',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(self::INVITED_EMAIL, $data['email']);
        $this->assertSame('ADMIN', $data['platformRole']);
        $this->assertFalse($data['active']);

        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailHtmlBodyContains($email, '/auth/jwt/set-password/?token=');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => self::INVITED_EMAIL]);
        $this->assertNotNull($user);
        $this->assertFalse($user->isActive());
        $this->assertSame(PlatformRoleEnum::ADMIN, $user->getPlatformRole());
        $this->assertSame('612345678', $user->getPhone());

        // Le compte inactif ne peut pas se connecter
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => self::INVITED_EMAIL,
            'password' => 'whatever-password',
        ]));
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        // Le flux set-password fonctionne sans adhésion à un institut
        $invitationToken = static::getContainer()->get(TokenService::class)->generateInvitationToken($user);

        $client->request('GET', '/api/auth/invitation/' . $invitationToken, [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseIsSuccessful();
        $invitation = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(self::INVITED_EMAIL, $invitation['email']);
        $this->assertNull($invitation['institute']);

        $client->request('POST', '/api/auth/accept-invitation/' . $invitationToken, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'password' => 'MotDePasse123',
        ]));
        $this->assertResponseIsSuccessful();

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => self::INVITED_EMAIL,
            'password' => 'MotDePasse123',
        ]));
        $this->assertResponseIsSuccessful();
    }

    public function testInviteUserAsNonAdminForbidden(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/admin/users/invite', [], [], $this->jsonHeaders($token), json_encode([
            'email' => self::INVITED_EMAIL,
            'firstname' => 'Inès',
            'lastname' => 'Invitée',
            'platformRole' => 'USER',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertEmailCount(0);
    }

    public function testInviteUserWithExistingEmailIsRefused(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::ADMIN_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        // Casse différente : la comparaison est insensible à la casse
        $client->request('POST', '/api/admin/users/invite', [], [], $this->jsonHeaders($token), json_encode([
            'email' => strtoupper(UserFixtures::USER2_EMAIL),
            'firstname' => 'Doublon',
            'lastname' => 'Doublon',
            'platformRole' => 'USER',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertSame(
            'Un compte existe déjà avec cette adresse email.',
            json_decode($client->getResponse()->getContent(), true)['detail']
        );
        $this->assertEmailCount(0);
    }

    public function testDeactivatedUserLosesAccessWithValidToken(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]);
        $user->setIsActive(false);
        $em->flush();

        $client->request('GET', '/api/users/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
