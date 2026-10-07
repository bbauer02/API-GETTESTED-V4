<?php

namespace App\Service;

use App\Entity\EnrollmentSession;
use App\Entity\Invoice;
use App\Entity\Session;
use App\Enum\BusinessTypeEnum;
use App\Enum\InvoiceTypeEnum;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Emails transactionnels envoyés aux candidats à chaque étape de leur inscription.
 *
 * Un échec d'envoi est journalisé mais n'interrompt jamais le traitement métier
 * (inscription, paiement, annulation…).
 */
class CandidateNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $frontendUrl,
    ) {
    }

    /** Inscription enregistrée (avec lien de paiement si la facture n'est pas réglée). */
    public function enrollmentConfirmed(EnrollmentSession $enrollment): void
    {
        $invoice = $this->enrollmentInvoice($enrollment);

        $this->send($enrollment, 'enrollment_confirmed', sprintf('Inscription enregistrée — %s', $this->sessionTitle($enrollment->getSession())), [
            'invoice' => $invoice ? $this->invoiceData($invoice) : null,
            'toPay' => $invoice !== null && $invoice->getStatus()->value === 'ISSUED' && $invoice->getTotalTTC() > 0,
        ]);
    }

    /** Paiement confirmé : reçu. */
    public function paymentReceived(Invoice $invoice): void
    {
        $enrollment = $invoice->getEnrollmentSession();
        if (!$enrollment || $invoice->getBusinessType() === BusinessTypeEnum::PLATFORM_COMMISSION) {
            return;
        }

        $this->send($enrollment, 'payment_received', sprintf('Paiement reçu — %s', $this->sessionTitle($enrollment->getSession())), [
            'invoice' => $this->invoiceData($invoice),
        ]);
    }

    /** Paiement refusé par la banque : inviter à réessayer. */
    public function paymentFailed(Invoice $invoice): void
    {
        $enrollment = $invoice->getEnrollmentSession();
        if (!$enrollment) {
            return;
        }

        $this->send($enrollment, 'payment_failed', 'Votre paiement n\'a pas abouti', [
            'invoice' => $this->invoiceData($invoice),
        ]);
    }

    /** Inscription annulée (par le candidat ou l'institut), avec le montant remboursé. */
    public function enrollmentCancelled(EnrollmentSession $enrollment, float $refundedAmount): void
    {
        $this->send($enrollment, 'enrollment_cancelled', sprintf('Inscription annulée — %s', $this->sessionTitle($enrollment->getSession())), [
            'refundedAmount' => $refundedAmount,
        ], withLink: false);
    }

    /** Session annulée par l'institut. */
    public function sessionCancelled(EnrollmentSession $enrollment, float $refundedAmount): void
    {
        $this->send($enrollment, 'session_cancelled', sprintf('Session annulée — %s', $this->sessionTitle($enrollment->getSession())), [
            'refundedAmount' => $refundedAmount,
        ]);
    }

    /** Inscription transférée vers une autre session. */
    public function enrollmentTransferred(EnrollmentSession $enrollment, Session $previousSession): void
    {
        $this->send($enrollment, 'enrollment_transferred', sprintf('Changement de session — %s', $this->sessionTitle($enrollment->getSession())), [
            'previousSession' => $this->sessionData($previousSession),
        ]);
    }

    /** Session validée : la convocation est téléchargeable. */
    public function convocationAvailable(EnrollmentSession $enrollment): void
    {
        $this->send($enrollment, 'convocation_available', sprintf('Votre convocation est disponible — %s', $this->sessionTitle($enrollment->getSession())));
    }

    /** Rappel quelques jours avant la première épreuve. */
    public function sessionReminder(EnrollmentSession $enrollment): void
    {
        $this->send($enrollment, 'session_reminder', sprintf('Rappel : votre session %s approche', $this->sessionTitle($enrollment->getSession())));
    }

    // ----------------------------------------------------------------------

    private function send(EnrollmentSession $enrollment, string $template, string $subject, array $context = [], bool $withLink = true): void
    {
        $user = $enrollment->getUser();
        $email = $user?->getEmail();
        if (!$email) {
            return;
        }

        try {
            $html = $this->twig->render(sprintf('email/candidate/%s.html.twig', $template), [
                'firstname' => $user->getFirstname(),
                'session' => $this->sessionData($enrollment->getSession()),
                'referenceNumber' => $enrollment->getReferenceNumber(),
                'exams' => $this->examsData($enrollment),
                'enrollmentUrl' => $withLink ? $this->enrollmentUrl($enrollment) : null,
                'searchUrl' => rtrim($this->frontendUrl, '/') . '/sessions',
                ...$context,
            ]);

            $this->mailer->send(
                (new Email())
                    ->to($email)
                    ->subject($subject . ' · GetTested')
                    ->html($html)
            );
        } catch (\Throwable $e) {
            $this->logger->error('Email candidat non envoyé', [
                'template' => $template,
                'enrollmentId' => (string) $enrollment->getId(),
                'exception' => $e,
            ]);
        }
    }

    private function enrollmentUrl(EnrollmentSession $enrollment): string
    {
        return sprintf('%s/dashboard/mes-inscriptions/%s', rtrim($this->frontendUrl, '/'), $enrollment->getId());
    }

    private function sessionTitle(?Session $session): string
    {
        $parts = array_filter([$session?->getAssessment()?->getLabel(), $session?->getLevel()?->getLabel()]);

        return $parts ? implode(' ', $parts) : 'votre session';
    }

    private function sessionData(?Session $session): array
    {
        $address = $session?->getInstitute()?->getAddress();

        return [
            'title' => $this->sessionTitle($session),
            'institute' => $session?->getInstitute()?->getLabel(),
            'instituteEmail' => $session?->getInstitute()?->getEmail(),
            'instituteAddress' => $address ? trim(sprintf('%s, %s %s', $address->getAddress1(), $address->getZipcode(), $address->getCity()), ', ') : null,
            'start' => $session?->getStart(),
            'end' => $session?->getEnd(),
        ];
    }

    private function examsData(EnrollmentSession $enrollment): array
    {
        $exams = [];
        foreach ($enrollment->getEnrollmentExams() as $enrollmentExam) {
            $scheduledExam = $enrollmentExam->getScheduledExam();
            if (!$scheduledExam) {
                continue;
            }

            $center = $scheduledExam->getExamCenter();
            $address = $scheduledExam->getAddress() ?? $center?->getAddress();

            $exams[] = [
                'label' => $scheduledExam->getExam()?->getLabel(),
                'start' => $scheduledExam->getStartDate(),
                'place' => implode(' — ', array_filter([
                    $center?->getLabel(),
                    $this->roomLabel($scheduledExam->getRoom()),
                    $address ? trim(sprintf('%s, %s %s', $address->getAddress1(), $address->getZipcode(), $address->getCity()), ', ') : null,
                ])),
            ];
        }

        usort($exams, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return $exams;
    }

    /** « 12 » → « salle 12 », « Salle Molière » reste tel quel. */
    private function roomLabel(?string $room): ?string
    {
        if (!$room) {
            return null;
        }

        return stripos($room, 'salle') === 0 ? $room : 'salle ' . $room;
    }

    private function enrollmentInvoice(EnrollmentSession $enrollment): ?Invoice
    {
        foreach ($enrollment->getInvoices() as $invoice) {
            if ($invoice->getInvoiceType() === InvoiceTypeEnum::INVOICE
                && $invoice->getBusinessType() !== BusinessTypeEnum::PLATFORM_COMMISSION
            ) {
                return $invoice;
            }
        }

        return null;
    }

    private function invoiceData(Invoice $invoice): array
    {
        return [
            'number' => $invoice->getInvoiceNumber(),
            'totalTTC' => $invoice->getTotalTTC(),
            'currency' => $invoice->getCurrency() ?: 'EUR',
        ];
    }
}
