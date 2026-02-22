<?php

namespace App\DataFixtures;

use App\Entity\Embeddable\Counterparty;
use App\Entity\EnrollmentSession;
use App\Entity\Institute;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\Payment;
use App\Enum\BusinessTypeEnum;
use App\Enum\InvoiceStatusEnum;
use App\Enum\InvoiceTypeEnum;
use App\Enum\OperationCategoryEnum;
use App\Enum\PaymentMethodEnum;
use App\Enum\PaymentStatusEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InvoiceFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            SessionFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var Institute $institute */
        $institute = $this->getReference('institute_1', Institute::class);
        /** @var EnrollmentSession $enrollment */
        $enrollment = $this->getReference('enrollment_christophe', EnrollmentSession::class);

        // Seller commun
        $seller = new Counterparty();
        $seller->setName($institute->getLabel());
        $seller->setAddress('123 rue du Général Degaule');
        $seller->setCity('Paris');
        $seller->setZipcode('75000');
        $seller->setCountryCode('FR');
        $seller->setSiren('123456789');
        $seller->setSiret('12345678901234');
        $seller->setLegalForm('SAS');
        $seller->setShareCapital('10 000 €');
        $seller->setRcsCity('Paris');

        // Buyer commun (Christophe)
        $buyer = new Counterparty();
        $buyer->setName('Christophe Lefebre');
        $buyer->setAddress('5 boulevard Voltaire');
        $buyer->setCity('Marseille');
        $buyer->setZipcode('13000');
        $buyer->setCountryCode('FR');

        // ==============================
        // Facture 1 — DRAFT pour l'enrollment de Christophe
        // ==============================
        $invoiceDraft = new Invoice();
        $invoiceDraft->setInstitute($institute);
        $invoiceDraft->setEnrollmentSession($enrollment);
        $invoiceDraft->setBusinessType(BusinessTypeEnum::ENROLLMENT);
        $invoiceDraft->setOperationCategory(OperationCategoryEnum::SERVICE);

        $sellerDraft = clone $seller;
        $invoiceDraft->setSeller($sellerDraft);
        $buyerDraft = clone $buyer;
        $invoiceDraft->setBuyer($buyerDraft);

        $lineDraft = new InvoiceLine();
        $lineDraft->setLabel('TOEIC Listening');
        $lineDraft->setQuantity(1);
        $lineDraft->setUnitPriceHT(100.0);
        $lineDraft->setTvaRate(20.0);
        $lineDraft->computeAmounts();
        $invoiceDraft->addLine($lineDraft);

        $manager->persist($lineDraft);
        $manager->persist($invoiceDraft);
        $this->addReference('invoice_draft', $invoiceDraft);

        // ==============================
        // Facture 2 — ISSUED avec numéro séquentiel + paiement COMPLETED
        // ==============================
        $invoiceIssued = new Invoice();
        $invoiceIssued->setInstitute($institute);
        $invoiceIssued->setEnrollmentSession($enrollment);
        $invoiceIssued->setBusinessType(BusinessTypeEnum::ENROLLMENT);
        $invoiceIssued->setOperationCategory(OperationCategoryEnum::SERVICE);
        $invoiceIssued->setStatus(InvoiceStatusEnum::ISSUED);
        $invoiceIssued->setInvoiceNumber('INV-2026-00001');
        $invoiceIssued->setInvoiceDate(new \DateTime('2026-02-20 10:00:00'));

        $sellerIssued = clone $seller;
        $invoiceIssued->setSeller($sellerIssued);
        $buyerIssued = clone $buyer;
        $invoiceIssued->setBuyer($buyerIssued);

        $lineIssued = new InvoiceLine();
        $lineIssued->setLabel('TOEIC Listening — Session Mars 2026');
        $lineIssued->setQuantity(1);
        $lineIssued->setUnitPriceHT(100.0);
        $lineIssued->setTvaRate(20.0);
        $lineIssued->computeAmounts();
        $invoiceIssued->addLine($lineIssued);

        $invoiceIssued->setTotalHT(100.0);
        $invoiceIssued->setTotalTVA(20.0);
        $invoiceIssued->setTotalTTC(120.0);

        $manager->persist($lineIssued);
        $manager->persist($invoiceIssued);
        $this->addReference('invoice_issued', $invoiceIssued);

        // Paiement COMPLETED pour la facture ISSUED
        $paymentCompleted = new Payment();
        $paymentCompleted->setInvoice($invoiceIssued);
        $paymentCompleted->setAmount(120.0);
        $paymentCompleted->setStatus(PaymentStatusEnum::COMPLETED);
        $paymentCompleted->setDate(new \DateTime('2026-02-20 11:00:00'));
        $paymentCompleted->setPaymentMethod(PaymentMethodEnum::STRIPE);
        $paymentCompleted->setStripePaymentIntentId('pi_test_123456');
        $manager->persist($paymentCompleted);
        $this->addReference('payment_completed', $paymentCompleted);

        // ==============================
        // Facture 3 — ISSUED pour tester les credit notes (avoir)
        // ==============================
        $invoiceForRefund = new Invoice();
        $invoiceForRefund->setInstitute($institute);
        $invoiceForRefund->setBusinessType(BusinessTypeEnum::ENROLLMENT);
        $invoiceForRefund->setOperationCategory(OperationCategoryEnum::SERVICE);
        $invoiceForRefund->setStatus(InvoiceStatusEnum::ISSUED);
        $invoiceForRefund->setInvoiceNumber('INV-2026-00002');
        $invoiceForRefund->setInvoiceDate(new \DateTime('2026-02-18 10:00:00'));

        $sellerRefund = clone $seller;
        $invoiceForRefund->setSeller($sellerRefund);
        $buyerRefund = clone $buyer;
        $invoiceForRefund->setBuyer($buyerRefund);

        $lineRefund = new InvoiceLine();
        $lineRefund->setLabel('TOEIC Reading — Session Mars 2026');
        $lineRefund->setQuantity(1);
        $lineRefund->setUnitPriceHT(80.0);
        $lineRefund->setTvaRate(20.0);
        $lineRefund->computeAmounts();
        $invoiceForRefund->addLine($lineRefund);

        $invoiceForRefund->setTotalHT(80.0);
        $invoiceForRefund->setTotalTVA(16.0);
        $invoiceForRefund->setTotalTTC(96.0);

        $manager->persist($lineRefund);
        $manager->persist($invoiceForRefund);
        $this->addReference('invoice_for_refund', $invoiceForRefund);

        // Paiement COMPLETED pour cette facture (à rembourser dans les tests)
        $paymentForRefund = new Payment();
        $paymentForRefund->setInvoice($invoiceForRefund);
        $paymentForRefund->setAmount(96.0);
        $paymentForRefund->setStatus(PaymentStatusEnum::COMPLETED);
        $paymentForRefund->setDate(new \DateTime('2026-02-18 11:00:00'));
        $paymentForRefund->setPaymentMethod(PaymentMethodEnum::BANK_TRANSFER);
        $manager->persist($paymentForRefund);
        $this->addReference('payment_for_refund', $paymentForRefund);

        $manager->flush();
    }
}
