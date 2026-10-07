<?php

namespace App\Tests\Api;

use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Institute;
use App\Entity\InstituteMembership;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\SessionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Droits par rôle dans un institut :
 * - STAFF : gestion opérationnelle des sessions, pas l'équipe ;
 * - TEACHER : uniquement les sessions dont il est examinateur.
 */
class InstituteRoleAccessTest extends WebTestCase
{
    use ApiTestTrait;

    /**
     * Donne à Christophe (USER2, client de l'institut 2) le rôle demandé dans cet institut.
     *
     * @return array{0: Institute, 1: User}
     */
    private function giveRole(EntityManagerInterface $em, InstituteRoleEnum $role): array
    {
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE2_LABEL]);
        $user = $em->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]);

        $membership = $em->getRepository(InstituteMembership::class)->findOneBy([
            'institute' => $institute,
            'user' => $user,
        ]);
        $membership->setRole($role);
        $em->flush();

        return [$institute, $user];
    }

    private function sessionsOf($client, string $token, Institute $institute): array
    {
        $client->request('GET', '/api/institutes/' . $institute->getId() . '/sessions', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        return json_decode($client->getResponse()->getContent(), true);
    }

    public function testTeacherOnlySeesSessionsWhereExaminator(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$institute, $user] = $this->giveRole($em, InstituteRoleEnum::TEACHER);
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        // Pas encore examinateur : aucune session
        $this->assertCount(0, $this->sessionsOf($client, $token, $institute));

        // Examinateur d'une épreuve d'une session : cette session seulement
        // (entités rechargées : la requête précédente a réinitialisé l'EntityManager)
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->find($user->getId());
        $session = $em->getRepository(Session::class)->findOneBy(['institute' => $institute->getId()]);
        $session->getScheduledExams()->first()->addExaminator($user);
        $em->flush();

        $sessions = $this->sessionsOf($client, $token, $institute);
        $this->assertCount(1, $sessions);
        $this->assertSame((string) $session->getId(), $sessions[0]['id']);
        // La liste ne porte que le nombre d'inscrits ; le détail de la session donne les inscrits
        // (nécessaires à la saisie des résultats)
        $this->assertArrayNotHasKey('enrollments', $sessions[0]);
        $this->assertArrayHasKey('enrollmentsCount', $sessions[0]);

        $client->request('GET', '/api/sessions/' . $session->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('enrollments', json_decode($client->getResponse()->getContent(), true));
    }

    public function testStaffCanManageSessionLifecycle(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$institute] = $this->giveRole($em, InstituteRoleEnum::STAFF);
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $session = $em->getRepository(Session::class)->findOneBy([
            'institute' => $institute,
            'status' => SessionStatusEnum::OPEN,
        ]);

        $client->request('PATCH', '/api/sessions/' . $session->getId() . '/transition', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['transition' => 'lock']));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testStaffCannotInviteTeamMembers(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        [$institute] = $this->giveRole($em, InstituteRoleEnum::STAFF);
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/memberships/invite', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'email' => 'nouveau.membre@example.com',
            'firstname' => 'Nouveau',
            'lastname' => 'Membre',
            'role' => 'TEACHER',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
