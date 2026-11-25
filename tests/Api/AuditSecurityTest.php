<?php

namespace App\Tests\Api;

use App\DataFixtures\AssessmentFixtures;
use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\DocumentTemplate;
use App\Entity\DocumentType;
use App\Entity\EnrollmentSession;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Entity\ScheduledExam;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\MembershipStatusEnum;
use App\Enum\PlatformRoleEnum;
use App\Tests\Fixtures\InvoiceTestData;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Règles de sécurité de l'audit du 25/11/2026 :
 * membres archivés, factures et inscriptions de l'institut selon le rôle, modèles de documents,
 * modification des utilisateurs par l'admin plateforme, URL de retour du checkout Stripe,
 * documents candidat indisponibles.
 */
class AuditSecurityTest extends WebTestCase
{
    use ApiTestTrait;
    use AuditTestTrait;

    private const IF = InstituteFixtures::INSTITUTE1_LABEL;
    private const TENRI = InstituteFixtures::INSTITUTE2_LABEL;

    /** Épreuve planifiée de la session du test personnalisé de l'Institut Français (sans sujet). */
    private function customScheduledExam(): ScheduledExam
    {
        $custom = $this->assessment(AssessmentFixtures::CUSTOM_LABEL);
        $session = $this->em()->getRepository(Session::class)->findOneBy(['assessment' => $custom]);

        return $this->em()->getRepository(ScheduledExam::class)->findOneBy(['session' => $session], ['startDate' => 'ASC']);
    }

    private function createTemplate(?string $instituteLabel, string $code, string $label): DocumentTemplate
    {
        $em = $this->em();
        $template = new DocumentTemplate();
        $template->setLabel($label);
        $template->setDocumentType($em->getRepository(DocumentType::class)->findOneBy(['code' => $code]));
        $template->setContent('<p>' . $label . '</p>');
        $template->setInstitute($instituteLabel !== null ? $this->institute($instituteLabel) : null);
        $em->persist($template);
        $em->flush();

        return $template;
    }

    // ========================
    // 1. Membre archivé
    // ========================

    public function testArchivedAdminLosesInvoicesStripeQuestionsAndSubjects(): void
    {
        static::createClient();
        $this->loadFixtures();

        $institute = $this->institute(self::IF);
        $custom = $this->assessment(AssessmentFixtures::CUSTOM_LABEL);
        $levelA1 = $this->level('A1');
        $scheduledExam = $this->customScheduledExam();

        // Ayaka, ADMIN de l'Institut Français, est archivée
        $this->setMembership(UserFixtures::USER1_EMAIL, self::IF, InstituteRoleEnum::ADMIN, MembershipStatusEnum::ARCHIVED);
        $token = $this->token(UserFixtures::USER1_EMAIL);

        [$status] = $this->api('GET', '/api/institutes/' . $institute->getId() . '/invoices', $token);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status, 'Factures de l\'institut');

        // Stripe Connect : refus avant tout appel à Stripe
        [$status, $data] = $this->api('GET', '/api/institutes/' . $institute->getId() . '/stripe/status', $token);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status, 'Statut Stripe');
        $this->assertStringContainsString('droits', $data['detail']);
        [$status] = $this->api('POST', '/api/institutes/' . $institute->getId() . '/stripe/session', $token);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status, 'Session Stripe');

        // Création de question (securityPostDenormalize QUESTION_CREATE)
        [$status] = $this->api('POST', '/api/mcq-questions', $token, [
            'assessment' => '/api/assessments/' . $custom->getId(),
            'level' => '/api/levels/' . $levelA1->getId(),
            'label' => 'Question d\'une archivée',
            'text' => 'Énoncé',
            'maxPoints' => 1,
            'isMultipleAnswer' => false,
            'choices' => [
                ['text' => 'Oui', 'weight' => 1, 'position' => 0],
                ['text' => 'Non', 'weight' => 0, 'position' => 1],
            ],
        ]);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status, 'Création de question');

        // Création de sujet (SubjectCreateProcessor)
        [$status] = $this->api('POST', '/api/subjects', $token, [
            'titre' => 'Sujet d\'une archivée',
            'scheduledExam' => '/api/scheduled-exams/' . $scheduledExam->getId(),
        ]);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status, 'Création de sujet');
    }

    public function testActiveAdminCanCreateSubject(): void
    {
        static::createClient();
        $this->loadFixtures();

        // Contrôle positif du test précédent : la même requête par l'ADMIN actif aboutit
        $scheduledExam = $this->customScheduledExam();
        $token = $this->token(UserFixtures::USER1_EMAIL);

        [$status, $data] = $this->api('POST', '/api/subjects', $token, [
            'titre' => 'Sujet de l\'admin',
            'scheduledExam' => '/api/scheduled-exams/' . $scheduledExam->getId(),
        ]);
        $this->assertSame(Response::HTTP_CREATED, $status);
        $this->assertSame('DRAFT', $data['status']);
    }

    // ========================
    // 2. Factures de l'institut : ADMIN actif seulement
    // ========================

    public function testInstituteInvoicesAdminAllowedStaffForbidden(): void
    {
        static::createClient();
        $this->loadFixtures();
        InvoiceTestData::load($this->em());

        $institute = $this->institute(self::IF);

        [$status, $data] = $this->api('GET', '/api/institutes/' . $institute->getId() . '/invoices', $this->token(UserFixtures::USER1_EMAIL));
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertNotEmpty($data);

        // Christophe devient STAFF de l'Institut Français : ni la liste, ni une facture
        $this->setMembership(UserFixtures::USER2_EMAIL, self::IF, InstituteRoleEnum::STAFF);
        $staff = $this->token(UserFixtures::USER2_EMAIL);

        [$status] = $this->api('GET', '/api/institutes/' . $institute->getId() . '/invoices', $staff);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status);

        // Facture sans inscription de Christophe (sinon il la verrait comme candidat)
        $other = $this->em()->getRepository(Invoice::class)->findOneBy(['invoiceNumber' => 'INV-2026-00002']);
        $this->assertNull($other->getEnrollmentSession());
        [$status] = $this->api('GET', '/api/invoices/' . $other->getId(), $staff);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status);
    }

    // ========================
    // 3. Inscriptions de l'institut : ADMIN et STAFF, pas TEACHER
    // ========================

    public function testInstituteEnrollmentsByRole(): void
    {
        static::createClient();
        $this->loadFixtures();

        $url = '/api/institutes/' . $this->institute(self::IF)->getId() . '/enrollments';

        [$status, $data] = $this->api('GET', $url, $this->token(UserFixtures::USER1_EMAIL));
        $this->assertSame(Response::HTTP_OK, $status, 'ADMIN');
        $this->assertNotEmpty($data);

        $this->setMembership(UserFixtures::USER2_EMAIL, self::IF, InstituteRoleEnum::STAFF);
        [$status] = $this->api('GET', $url, $this->token(UserFixtures::USER2_EMAIL));
        $this->assertSame(Response::HTTP_OK, $status, 'STAFF');

        $this->setMembership(UserFixtures::USER2_EMAIL, self::IF, InstituteRoleEnum::TEACHER);
        [$status] = $this->api('GET', $url, $this->token(UserFixtures::USER2_EMAIL));
        $this->assertSame(Response::HTTP_FORBIDDEN, $status, 'TEACHER');
    }

    // ========================
    // 7. Modèles de documents
    // ========================

    public function testDocumentTemplatesAccessByInstitute(): void
    {
        static::createClient();
        $this->loadFixtures();

        $own = $this->createTemplate(self::IF, 'CONVOCATION', 'Convocation IF');
        $foreign = $this->createTemplate(self::TENRI, 'CONVOCATION', 'Convocation Tenri');
        $ownId = $own->getId();
        $foreignId = $foreign->getId();

        $admin = $this->token(UserFixtures::USER1_EMAIL);

        [$status, $data] = $this->api('GET', '/api/document-templates/' . $ownId, $admin);
        $this->assertSame(Response::HTTP_OK, $status, 'Modèle de son institut');
        $this->assertSame('Convocation IF', $data['label']);

        [$status] = $this->api('GET', '/api/document-templates/' . $foreignId, $admin);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status, 'Modèle d\'un autre institut');

        // STAFF de l'Institut Français : modification autorisée (INSTITUTE_DOCUMENTS_MANAGE)
        $this->setMembership(UserFixtures::USER2_EMAIL, self::IF, InstituteRoleEnum::STAFF);
        $staff = $this->token(UserFixtures::USER2_EMAIL);

        [$status, $data] = $this->api('PATCH', '/api/document-templates/' . $ownId, $staff, ['label' => 'Convocation modifiée']);
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertSame('Convocation modifiée', $data['label']);
        $this->assertSame('Convocation modifiée', $this->em()->getRepository(DocumentTemplate::class)->find($ownId)->getLabel());

        // Mais pas celui d'un autre institut
        [$status] = $this->api('PATCH', '/api/document-templates/' . $foreignId, $staff, ['label' => 'Piratage']);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status);
        $this->em()->clear();
        $this->assertSame('Convocation Tenri', $this->em()->getRepository(DocumentTemplate::class)->find($foreignId)->getLabel());
    }

    // ========================
    // 8. PATCH /users/{id} par l'admin plateforme
    // ========================

    public function testAdminPatchUserDuplicateEmailIs422(): void
    {
        static::createClient();
        $this->loadFixtures();

        $target = $this->user(UserFixtures::USER2_EMAIL);
        [$status, $data] = $this->api('PATCH', '/api/users/' . $target->getId(), $this->token(UserFixtures::ADMIN_EMAIL), [
            'email' => UserFixtures::USER1_EMAIL,
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $status);
        $this->assertStringContainsString('Cette adresse email est déjà utilisée.', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public function testAdminCannotDeactivateOrDemoteHimself(): void
    {
        static::createClient();
        $this->loadFixtures();

        $admin = $this->user(UserFixtures::ADMIN_EMAIL);
        $adminId = $admin->getId();
        $token = $this->token(UserFixtures::ADMIN_EMAIL);

        [$status] = $this->api('PATCH', '/api/users/' . $adminId, $token, ['isActive' => false]);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status, 'Désactivation de son propre compte');

        [$status] = $this->api('PATCH', '/api/users/' . $adminId, $token, ['platformRole' => 'USER']);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status, 'Rétrogradation de son propre compte');

        // Relecture en base (l'entité dénormalisée par la requête refusée reste en mémoire)
        $this->em()->clear();
        $admin = $this->em()->getRepository(User::class)->find($adminId);
        $this->assertTrue($admin->isActive());
        $this->assertSame(PlatformRoleEnum::ADMIN, $admin->getPlatformRole());

        // Il peut en revanche désactiver un autre compte
        $otherId = $this->user(UserFixtures::USER2_EMAIL)->getId();
        [$status] = $this->api('PATCH', '/api/users/' . $otherId, $token, ['isActive' => false]);
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertFalse($this->em()->getRepository(User::class)->find($otherId)->isActive());
    }

    // ========================
    // 9. Checkout Stripe : URL de retour limitées au front
    // ========================

    public function testCheckoutRejectsForeignReturnUrls(): void
    {
        static::createClient();
        $this->loadFixtures();
        InvoiceTestData::load($this->em());

        // Facture émise de Christophe (institut avec compte Stripe actif)
        $invoice = $this->em()->getRepository(Invoice::class)->findOneBy(['invoiceNumber' => 'INV-2026-00001']);
        $invoiceId = $invoice->getId();
        $paymentsBefore = $this->em()->getRepository(Payment::class)->count(['invoice' => $invoice]);

        $token = $this->token(UserFixtures::USER2_EMAIL);

        foreach ([
            ['https://evil.example/ok', 'https://evil.example/ko'],
            ['http://localhost:3033/paiement/ok', 'https://evil.example/ko'],
            ['javascript:alert(1)', 'javascript:alert(1)'],
        ] as [$success, $cancel]) {
            [$status] = $this->api('POST', '/api/invoices/' . $invoiceId . '/checkout', $token, [
                'successUrl' => $success,
                'cancelUrl' => $cancel,
            ]);
            $this->assertSame(Response::HTTP_BAD_REQUEST, $status, $success . ' / ' . $cancel);
        }

        // Refus avant toute création de paiement (et donc avant tout appel à Stripe)
        $invoice = $this->em()->getRepository(Invoice::class)->find($invoiceId);
        $this->assertSame($paymentsBefore, $this->em()->getRepository(Payment::class)->count(['invoice' => $invoice]));
    }

    // ========================
    // 10. Documents candidat : pas de modèle pour un document indisponible
    // ========================

    public function testUnavailableCandidateDocumentHasNoTemplate(): void
    {
        static::createClient();
        $this->loadFixtures();

        // Modèles maîtres pour un document disponible (confirmation) et un indisponible (convocation, session OPEN)
        $this->createTemplate(null, 'REGISTRATION_CONFIRMATION', 'Confirmation maître');
        $this->createTemplate(null, 'CONVOCATION', 'Convocation maître');

        $enrollment = $this->em()->getRepository(EnrollmentSession::class)->findOneBy([
            'user' => $this->user(UserFixtures::USER2_EMAIL),
        ]);

        [$status, $data] = $this->api('GET', '/api/enrollment-sessions/' . $enrollment->getId() . '/documents', $this->token(UserFixtures::USER2_EMAIL));
        $this->assertSame(Response::HTTP_OK, $status);

        $byCode = [];
        foreach ($data['items'] as $item) {
            $byCode[$item['documentType']['code']] = $item;
        }

        $this->assertFalse($byCode['CONVOCATION']['availability']['available']);
        $this->assertArrayHasKey('template', $byCode['CONVOCATION']);
        $this->assertNull($byCode['CONVOCATION']['template']);

        $this->assertTrue($byCode['REGISTRATION_CONFIRMATION']['availability']['available']);
        $this->assertNotNull($byCode['REGISTRATION_CONFIRMATION']['template']);
        $this->assertSame('Confirmation maître', $byCode['REGISTRATION_CONFIRMATION']['template']['label']);
    }
}
