<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sécurité des comptes : liens à usage unique, sessions fermées au changement de mot de passe,
 * invitation sans effet sur un compte désactivé, changement d'email protégé par le mot de passe.
 */
class AccountSecurityTest extends WebTestCase
{
    use ApiTestTrait;

    private function json($client, string $method, string $url, array $body = [], ?string $token = null): array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        if ($method === 'PATCH') {
            $server['CONTENT_TYPE'] = 'application/merge-patch+json';
        }
        $client->request($method, $url, [], [], $server, json_encode($body));

        return [$client->getResponse()->getStatusCode(), json_decode($client->getResponse()->getContent() ?: 'null', true)];
    }

    private function user(string $email): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function testResetLinkCanOnlyBeUsedOnceAndClosesSessions(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $oldToken = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $link = static::getContainer()->get(TokenService::class)->generateResetToken($this->user(UserFixtures::USER2_EMAIL));

        [$status] = $this->json($client, 'POST', '/api/auth/reset-password/' . $link, ['newPassword' => 'nouveauMotDePasse1']);
        $this->assertSame(Response::HTTP_OK, $status);

        // Le même lien ne sert plus
        [$status] = $this->json($client, 'POST', '/api/auth/reset-password/' . $link, ['newPassword' => 'autreMotDePasse2']);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $status);

        // La session ouverte avant la réinitialisation est fermée
        [$status] = $this->json($client, 'GET', '/api/users/me', [], $oldToken);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $status);

        $this->assertNotEmpty($this->getJwtToken(UserFixtures::USER2_EMAIL, 'nouveauMotDePasse1'));
    }

    public function testChangePasswordKeepsCurrentSessionAndClosesOthers(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $other = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);
        $current = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        [$status, $data] = $this->json($client, 'POST', '/api/me/change-password', [
            'currentPassword' => UserFixtures::DEFAULT_PASSWORD,
            'newPassword' => 'nouveauMotDePasse1',
        ], $current);
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertNotEmpty($data['access_token']);
        $this->assertNotEmpty($data['refresh_token']);

        [$status] = $this->json($client, 'GET', '/api/users/me', [], $other);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $status);

        [$status] = $this->json($client, 'GET', '/api/users/me', [], $data['access_token']);
        $this->assertSame(Response::HTTP_OK, $status);
    }

    public function testInvitationCannotReactivateAnAcceptedAccount(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->user(UserFixtures::INACTIVE_EMAIL);
        // Lien émis, puis compte activé (mot de passe défini) puis désactivé par un admin
        $link = static::getContainer()->get(TokenService::class)->generateInvitationToken($user);
        $user = $em->getRepository(User::class)->find($user->getId());
        $user->setPassword('$2y$13$autreHashQuelconqueAutreHashQuelconqueAutreHashQuelco');
        $user->setIsActive(false);
        $em->flush();

        [$status] = $this->json($client, 'POST', '/api/auth/accept-invitation/' . $link, ['password' => 'motDePasseInvite1']);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $status);
        $this->assertFalse($this->user(UserFixtures::INACTIVE_EMAIL)->isActive());
    }

    public function testEmailChangeRequiresCurrentPassword(): void
    {
        $client = static::createClient();
        $this->loadFixtures();
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        [$status] = $this->json($client, 'PATCH', '/api/users/me', ['email' => 'nouvelle.adresse@example.com'], $token);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $status);
        $this->assertNotNull($this->user(UserFixtures::USER2_EMAIL));

        [$status] = $this->json($client, 'PATCH', '/api/users/me', [
            'email' => 'nouvelle.adresse@example.com',
            'currentPassword' => UserFixtures::DEFAULT_PASSWORD,
        ], $token);
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertNotNull($this->user('nouvelle.adresse@example.com'));
    }

    public function testLogoutRevokesRefreshToken(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        [, $login] = $this->json($client, 'POST', '/api/auth/login', [
            'email' => UserFixtures::USER2_EMAIL,
            'password' => UserFixtures::DEFAULT_PASSWORD,
        ]);

        [$status] = $this->json($client, 'POST', '/api/auth/logout', ['refresh_token' => $login['refresh_token']]);
        $this->assertSame(Response::HTTP_NO_CONTENT, $status);

        [$status] = $this->json($client, 'POST', '/api/auth/token/refresh', ['refresh_token' => $login['refresh_token']]);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $status);
    }
}
