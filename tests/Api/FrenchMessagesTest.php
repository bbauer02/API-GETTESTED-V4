<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Messages de l'API en français (symfony/translation, locale fr) : validation et authentification.
 */
class FrenchMessagesTest extends WebTestCase
{
    use ApiTestTrait;
    use AuditTestTrait;

    public function testValidationMessagesAreInFrench(): void
    {
        static::createClient();
        $this->loadFixtures();

        [$status, $data] = $this->api('POST', '/api/auth/register', null, [
            'civility' => 'M',
            'firstname' => '',
            'lastname' => 'Sans-Prénom',
            'email' => 'sans.prenom@example.com',
            'password' => 'motdepasse123',
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $status);
        $messages = array_column($data['violations'] ?? [], 'message', 'propertyPath');
        $this->assertSame('Cette valeur ne doit pas être vide.', $messages['firstname'] ?? null, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public function testRefusedLoginMessageIsInFrench(): void
    {
        static::createClient();
        $this->loadFixtures();

        foreach ([
            UserFixtures::USER1_EMAIL => 'mauvais-mot-de-passe',
            'inconnu@example.com' => UserFixtures::DEFAULT_PASSWORD,
        ] as $email => $password) {
            [$status, $data] = $this->api('POST', '/api/auth/login', null, [
                'email' => $email,
                'password' => $password,
            ]);

            $this->assertSame(Response::HTTP_UNAUTHORIZED, $status, $email);
            $this->assertSame('Identifiants invalides.', $data['message'] ?? null, $email);
        }
    }
}
