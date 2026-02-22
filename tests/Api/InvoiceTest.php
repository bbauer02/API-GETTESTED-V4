<?php

namespace App\Tests\Api;

use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\EnrollmentSession;
use App\Entity\Institute;
use App\Entity\Invoice;
use App\Entity\Payment;
use App\Enum\InvoiceStatusEnum;
use App\Enum\PaymentStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class InvoiceTest extends WebTestCase
{
    use ApiTestTrait;

    // ========================
    // POST /institutes/{id}/invoices — Création facture
    // ========================

    public function testCreateInvoiceDraftFromEnrollment(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Ayaka (ADMIN Institut Français) crée une facture pour l'enrollment de Christophe
        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);
        $enrollment = $em->getRepository(EnrollmentSession::class)->findOneBy([
            'user' => $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]),
        ]);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/invoices', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'businessType' => 'ENROLLMENT',
            'enrollmentSession' => '/api/enrollment_sessions/' . $enrollment->getId(),
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('DRAFT', $data['status']);
        $this->assertNotEmpty($data['seller']['name']);
        $this->assertNotEmpty($data['buyer']['name']);
        $this->assertNotEmpty($data['lines']);
    }

    public function testCreateInvoiceAccessDenied(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe (CUSTOMER Tenri, pas admin de Institut Français) ne peut pas créer
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('POST', '/api/institutes/' . $institute->getId() . '/invoices', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'businessType' => 'ENROLLMENT',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ========================
    // POST /invoices/{id}/lines — Ajout de lignes
    // ========================

    public function testAddLineToInvoiceDraft(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $invoice = $em->getRepository(Invoice::class)->findOneBy(['status' => InvoiceStatusEnum::DRAFT]);

        $client->request('POST', '/api/invoices/' . $invoice->getId() . '/lines', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'Frais de dossier',
            'unitPriceHT' => 25.0,
            'quantity' => 1,
            'tvaRate' => 20.0,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Frais de dossier', $data['label']);
        $this->assertEquals(25.0, $data['totalHT']);
        $this->assertEquals(5.0, $data['tvaAmount']);
        $this->assertEquals(30.0, $data['totalTTC']);
    }

    // ========================
    // PATCH /invoices/{id}/issue — Émission
    // ========================

    public function testIssueInvoice(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $invoice = $em->getRepository(Invoice::class)->findOneBy(['status' => InvoiceStatusEnum::DRAFT]);

        $client->request('PATCH', '/api/invoices/' . $invoice->getId() . '/issue', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('ISSUED', $data['status']);
        $this->assertNotNull($data['invoiceNumber']);
        $this->assertNotNull($data['invoiceDate']);
        $this->assertGreaterThan(0, $data['totalHT']);
        $this->assertGreaterThan(0, $data['totalTTC']);
    }

    // ========================
    // PATCH /invoices/{id} — Immutabilité après émission
    // ========================

    public function testPatchIssuedInvoiceForbidden(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $invoice = $em->getRepository(Invoice::class)->findOneBy(['status' => InvoiceStatusEnum::ISSUED]);

        $client->request('PATCH', '/api/invoices/' . $invoice->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'paymentTerms' => 'Net 30',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ========================
    // POST /invoices/{id}/payments — Création paiement
    // ========================

    public function testCreatePaymentForIssuedInvoice(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        // Prendre la facture ISSUED qui n'a pas encore de paiement couvrant le total
        $invoice = $em->getRepository(Invoice::class)->findOneBy([
            'status' => InvoiceStatusEnum::ISSUED,
            'invoiceNumber' => 'INV-2026-00002',
        ]);

        $client->request('POST', '/api/invoices/' . $invoice->getId() . '/payments', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'amount' => 96.0,
            'paymentMethod' => 'STRIPE',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('PENDING', $data['status']);
        $this->assertEquals(96.0, $data['amount']);
    }

    // ========================
    // PATCH /payments/{id}/complete — Complétion
    // ========================

    public function testCompletePaymentAndInvoicePaid(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        // Créer d'abord un paiement PENDING
        $invoice = $em->getRepository(Invoice::class)->findOneBy([
            'invoiceNumber' => 'INV-2026-00002',
        ]);

        $payment = new Payment();
        $payment->setInvoice($invoice);
        $payment->setAmount(96.0);
        $payment->setStatus(PaymentStatusEnum::PENDING);
        $payment->setDate(new \DateTime());
        $payment->setPaymentMethod(\App\Enum\PaymentMethodEnum::STRIPE);
        $em->persist($payment);
        $em->flush();

        $paymentId = $payment->getId();
        $invoiceId = $invoice->getId();

        $client->request('PATCH', '/api/payments/' . $paymentId . '/complete', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('COMPLETED', $data['status']);

        // Vérifier que la facture est passée en PAID via GET
        $client->request('GET', '/api/invoices/' . $invoiceId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $invoiceData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('PAID', $invoiceData['status']);
    }

    // ========================
    // PATCH /payments/{id}/refund — Remboursement + avoir
    // ========================

    public function testRefundPaymentCreatesCredit(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'status' => PaymentStatusEnum::COMPLETED,
            'stripePaymentIntentId' => null, // le payment BANK_TRANSFER
        ]);

        $client->request('PATCH', '/api/payments/' . $payment->getId() . '/refund', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('REFUNDED', $data['status']);

        // Vérifier qu'un avoir (credit note) a été créé
        $creditNotes = $em->getRepository(Invoice::class)->findBy([
            'invoiceType' => \App\Enum\InvoiceTypeEnum::CREDIT_NOTE,
        ]);
        $this->assertNotEmpty($creditNotes);
    }

    // ========================
    // GET /enrollment_sessions/{id} — Lecture facture par le candidat
    // ========================

    public function testViewInvoiceAsEnrollmentOwner(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe peut voir une facture liée à son enrollment
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $enrollment = $em->getRepository(EnrollmentSession::class)->findOneBy([
            'user' => $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => UserFixtures::USER2_EMAIL]),
        ]);

        // Trouver une facture liée à cet enrollment
        $invoice = $em->getRepository(Invoice::class)->findOneBy([
            'enrollmentSession' => $enrollment,
        ]);

        $client->request('GET', '/api/invoices/' . $invoice->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('lines', $data);
    }

    // ========================
    // Accès non autorisé
    // ========================

    public function testViewInvoiceUnauthorized(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $invoice = $em->getRepository(Invoice::class)->findOneBy([]);

        $client->request('GET', '/api/invoices/' . $invoice->getId(), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    // ========================
    // GET /institutes/{id}/invoices — Liste factures institut
    // ========================

    public function testListInstituteInvoicesAsAdmin(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('GET', '/api/institutes/' . $institute->getId() . '/invoices', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertGreaterThanOrEqual(3, count($data));
    }

    public function testListInstituteInvoicesAsCustomerForbidden(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        // Christophe n'est pas admin/staff de Institut Français
        $token = $this->getJwtToken(UserFixtures::USER2_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $institute = $em->getRepository(Institute::class)->findOneBy(['label' => InstituteFixtures::INSTITUTE1_LABEL]);

        $client->request('GET', '/api/institutes/' . $institute->getId() . '/invoices', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ========================
    // Edge cases — Ajout ligne sur facture ISSUED
    // ========================

    public function testAddLineToIssuedInvoiceRejected(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $invoice = $em->getRepository(Invoice::class)->findOneBy(['status' => InvoiceStatusEnum::ISSUED]);

        $client->request('POST', '/api/invoices/' . $invoice->getId() . '/lines', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'label' => 'Ligne interdite',
            'unitPriceHT' => 50.0,
            'quantity' => 1,
            'tvaRate' => 20.0,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ========================
    // Edge cases — Paiement sur facture DRAFT
    // ========================

    public function testCreatePaymentOnDraftInvoiceRejected(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $invoice = $em->getRepository(Invoice::class)->findOneBy(['status' => InvoiceStatusEnum::DRAFT]);

        $client->request('POST', '/api/invoices/' . $invoice->getId() . '/payments', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([
            'amount' => 50.0,
            'paymentMethod' => 'STRIPE',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ========================
    // Edge cases — Double émission
    // ========================

    public function testDoubleIssueRejected(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $invoice = $em->getRepository(Invoice::class)->findOneBy(['status' => InvoiceStatusEnum::ISSUED]);

        $client->request('PATCH', '/api/invoices/' . $invoice->getId() . '/issue', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ========================
    // Edge cases — Paiement partiel ne passe pas en PAID
    // ========================

    public function testPartialPaymentKeepsInvoiceIssued(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        // Émettre la facture DRAFT (totalTTC = 120.0 : 100 HT + 20% TVA)
        $invoice = $em->getRepository(Invoice::class)->findOneBy(['status' => InvoiceStatusEnum::DRAFT]);
        $invoiceId = $invoice->getId();

        $client->request('PATCH', '/api/invoices/' . $invoiceId . '/issue', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Créer un paiement partiel (50 < 120 TTC)
        $em->clear();
        $invoice = $em->getRepository(Invoice::class)->find($invoiceId);

        $payment = new Payment();
        $payment->setInvoice($invoice);
        $payment->setAmount(50.0);
        $payment->setStatus(PaymentStatusEnum::PENDING);
        $payment->setDate(new \DateTime());
        $payment->setPaymentMethod(\App\Enum\PaymentMethodEnum::STRIPE);
        $em->persist($payment);
        $em->flush();

        $paymentId = $payment->getId();

        // Compléter le paiement partiel
        $client->request('PATCH', '/api/payments/' . $paymentId . '/complete', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('COMPLETED', $data['status']);

        // Vérifier que la facture reste ISSUED (paiement partiel)
        $client->request('GET', '/api/invoices/' . $invoiceId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $invoiceData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('ISSUED', $invoiceData['status']);
    }

    // ========================
    // Edge cases — Refund annule la facture originale
    // ========================

    public function testRefundCancelsOriginalInvoice(): void
    {
        $client = static::createClient();
        $this->loadFixtures();

        $token = $this->getJwtToken(UserFixtures::USER1_EMAIL, UserFixtures::DEFAULT_PASSWORD);

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $payment = $em->getRepository(Payment::class)->findOneBy([
            'status' => PaymentStatusEnum::COMPLETED,
            'stripePaymentIntentId' => null,
        ]);

        $invoiceId = $payment->getInvoice()->getId();

        $client->request('PATCH', '/api/payments/' . $payment->getId() . '/refund', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Vérifier que la facture originale est CANCELLED
        $client->request('GET', '/api/invoices/' . $invoiceId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $invoiceData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('CANCELLED', $invoiceData['status']);

        // Vérifier que l'avoir a des lignes négatives avec la bonne TVA
        $em->clear();
        $creditNotes = $em->getRepository(Invoice::class)->findBy([
            'invoiceType' => \App\Enum\InvoiceTypeEnum::CREDIT_NOTE,
        ]);
        $this->assertNotEmpty($creditNotes);
        $creditNote = $creditNotes[0];
        $this->assertLessThan(0, $creditNote->getTotalHT());
        $this->assertLessThan(0, $creditNote->getTotalTTC());
        $this->assertNotEmpty($creditNote->getLines());
        foreach ($creditNote->getLines() as $line) {
            $this->assertLessThan(0, $line->getUnitPriceHT());
            $this->assertEquals(20.0, $line->getTvaRate());
        }
    }
}
