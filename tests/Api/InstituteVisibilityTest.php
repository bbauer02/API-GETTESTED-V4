<?php

namespace App\Tests\Api;

use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Enum\InstituteStatusEnum;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /api/institutes : visibilité selon le lecteur (InstituteVisibilityExtension)
 * et détail complet réservé à la plateforme et à l'équipe de l'institut (InstituteNormalizer).
 */
class InstituteVisibilityTest extends WebTestCase
{
    use ApiTestTrait;
    use AuditTestTrait;

    /** Champs du groupe complet `institute:read` qui ne doivent pas sortir du groupe public. */
    private const SENSITIVE_FIELDS = ['email', 'siren', 'siret', 'vatNumber', 'legalForm', 'shareCapital', 'rcsCity', 'stripeAccount', 'commissionPercent'];

    /** Tenri en attente de validation, email renseigné sur l'Institut Français (sinon absent car nul). */
    private function prepare(): void
    {
        $em = $this->em();
        $this->institute(InstituteFixtures::INSTITUTE2_LABEL)->setStatus(InstituteStatusEnum::PENDING_REVIEW);
        $this->institute(InstituteFixtures::INSTITUTE1_LABEL)->setEmail('contact@institut-francais.example');
        $em->flush();
    }

    /** @return array<string, array> instituts indexés par libellé */
    private function byLabel(array $list): array
    {
        $result = [];
        foreach ($list as $institute) {
            $result[$institute['label']] = $institute;
        }

        return $result;
    }

    private function assertNoSensitiveField(array $institute): void
    {
        foreach (self::SENSITIVE_FIELDS as $field) {
            $this->assertArrayNotHasKey($field, $institute, $institute['label'] . ' expose ' . $field);
        }
    }

    public function testAnonymousSeesOnlyActiveInstitutesWithPublicFields(): void
    {
        static::createClient();
        $this->loadFixtures();
        $this->prepare();
        $tenriId = $this->institute(InstituteFixtures::INSTITUTE2_LABEL)->getId();
        $ifId = $this->institute(InstituteFixtures::INSTITUTE1_LABEL)->getId();

        [$status, $list] = $this->api('GET', '/api/institutes');
        $this->assertSame(Response::HTTP_OK, $status);
        $institutes = $this->byLabel($list);

        $this->assertArrayHasKey(InstituteFixtures::INSTITUTE1_LABEL, $institutes);
        $this->assertArrayNotHasKey(InstituteFixtures::INSTITUTE2_LABEL, $institutes, 'Institut en attente de validation visible');
        foreach ($institutes as $institute) {
            $this->assertSame('ACTIVE', $institute['status']);
            $this->assertNoSensitiveField($institute);
        }

        // Détail : même groupe public ; institut non validé introuvable
        [$status, $detail] = $this->api('GET', '/api/institutes/' . $ifId);
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertNoSensitiveField($detail);
        $this->assertSame(InstituteFixtures::INSTITUTE1_LABEL, $detail['label']);

        [$status] = $this->api('GET', '/api/institutes/' . $tenriId);
        $this->assertSame(Response::HTTP_NOT_FOUND, $status);
    }

    public function testPlatformAdminSeesEverythingIncludingPendingInstitutes(): void
    {
        static::createClient();
        $this->loadFixtures();
        $this->prepare();

        [$status, $list] = $this->api('GET', '/api/institutes', $this->token(UserFixtures::ADMIN_EMAIL));
        $this->assertSame(Response::HTTP_OK, $status);
        $institutes = $this->byLabel($list);

        $this->assertArrayHasKey(InstituteFixtures::INSTITUTE2_LABEL, $institutes);
        $this->assertSame('PENDING_REVIEW', $institutes[InstituteFixtures::INSTITUTE2_LABEL]['status']);

        $if = $institutes[InstituteFixtures::INSTITUTE1_LABEL];
        $this->assertSame('44306184100015', $if['siret']);
        $this->assertSame('443061841', $if['siren']);
        $this->assertSame('FR27443061841', $if['vatNumber']);
        $this->assertSame('contact@institut-francais.example', $if['email']);
        $this->assertArrayHasKey('stripeAccount', $if);
    }

    public function testMemberSeesFullDetailOfOwnInstituteOnly(): void
    {
        static::createClient();
        $this->loadFixtures();
        $this->prepare();
        // Tenri revalidé pour pouvoir comparer avec un institut visible dont Ayaka n'est pas membre
        $this->institute(InstituteFixtures::INSTITUTE2_LABEL)->setStatus(InstituteStatusEnum::ACTIVE);
        $this->em()->flush();
        $ifId = $this->institute(InstituteFixtures::INSTITUTE1_LABEL)->getId();
        $tenriId = $this->institute(InstituteFixtures::INSTITUTE2_LABEL)->getId();

        // Ayaka, ADMIN de l'Institut Français
        $token = $this->token(UserFixtures::USER1_EMAIL);

        [$status, $own] = $this->api('GET', '/api/institutes/' . $ifId, $token);
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertSame('44306184100015', $own['siret']);
        $this->assertSame('FR27443061841', $own['vatNumber']);
        $this->assertSame('contact@institut-francais.example', $own['email']);
        $this->assertArrayHasKey('stripeAccount', $own);

        [$status, $other] = $this->api('GET', '/api/institutes/' . $tenriId, $token);
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertNoSensitiveField($other);

        // Dans la liste : détail complet pour son institut seulement
        [, $list] = $this->api('GET', '/api/institutes', $token);
        $institutes = $this->byLabel($list);
        $this->assertArrayHasKey('siret', $institutes[InstituteFixtures::INSTITUTE1_LABEL]);
        $this->assertNoSensitiveField($institutes[InstituteFixtures::INSTITUTE2_LABEL]);
    }

    public function testPendingInstituteVisibleToItsTeamButNotToCandidates(): void
    {
        static::createClient();
        $this->loadFixtures();
        $this->prepare();
        $tenriId = $this->institute(InstituteFixtures::INSTITUTE2_LABEL)->getId();

        // Christophe est client (CUSTOMER) de Tenri : pas d'accès à un institut non validé
        [$status] = $this->api('GET', '/api/institutes/' . $tenriId, $this->token(UserFixtures::USER2_EMAIL));
        $this->assertSame(Response::HTTP_NOT_FOUND, $status);

        // Ayaka, ADMIN de l'Institut Français, ne le voit pas non plus
        [, $list] = $this->api('GET', '/api/institutes', $this->token(UserFixtures::USER1_EMAIL));
        $this->assertArrayNotHasKey(InstituteFixtures::INSTITUTE2_LABEL, $this->byLabel($list));

        // Devenu STAFF de Tenri, Christophe voit son institut en attente, avec le détail complet
        $this->setMembership(UserFixtures::USER2_EMAIL, InstituteFixtures::INSTITUTE2_LABEL, \App\Enum\InstituteRoleEnum::STAFF);
        [$status, $detail] = $this->api('GET', '/api/institutes/' . $tenriId, $this->token(UserFixtures::USER2_EMAIL));
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertSame('PENDING_REVIEW', $detail['status']);
        $this->assertSame('51234567800021', $detail['siret']);
    }
}
