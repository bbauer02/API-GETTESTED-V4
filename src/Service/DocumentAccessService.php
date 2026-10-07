<?php

namespace App\Service;

use App\Entity\DocumentType;
use App\Entity\EnrollmentSession;
use App\Entity\Session;
use App\Enum\EnrollmentExamStatusEnum;
use App\Enum\InvoiceStatusEnum;
use App\Enum\InvoiceTypeEnum;
use App\Enum\SessionStatusEnum;

/**
 * Règles de disponibilité des documents candidats par code de DocumentType.
 */
class DocumentAccessService
{
    public const REGISTRATION_CONFIRMATION = 'REGISTRATION_CONFIRMATION';
    public const REGISTRATION_CERTIFICATE = 'REGISTRATION_CERTIFICATE';
    public const CONVOCATION = 'CONVOCATION';
    public const ATTENDANCE_CERTIFICATE = 'ATTENDANCE_CERTIFICATE';
    public const PAYMENT_CERTIFICATE = 'PAYMENT_CERTIFICATE';

    /** @var array<string, string> code => label */
    public const TYPES = [
        self::REGISTRATION_CONFIRMATION => "Confirmation d'inscription",
        self::REGISTRATION_CERTIFICATE => "Attestation d'inscription",
        self::CONVOCATION => 'Convocation',
        self::ATTENDANCE_CERTIFICATE => 'Attestation de présence',
        self::PAYMENT_CERTIFICATE => 'Attestation de paiement',
    ];

    /**
     * @return array{available: bool, reason: ?string, availableFrom: ?string}
     */
    public function availability(DocumentType $documentType, Session $session, ?EnrollmentSession $enrollment = null, ?\DateTimeInterface $now = null): array
    {
        $now ??= new \DateTime();

        if ($session->getStatus() === SessionStatusEnum::CANCELLED) {
            return $this->result(false, 'La session est annulée.');
        }

        return match ($documentType->getCode()) {
            self::REGISTRATION_CONFIRMATION, self::REGISTRATION_CERTIFICATE => $this->result(true),
            self::CONVOCATION => $this->convocationAvailability($session, $enrollment),
            self::ATTENDANCE_CERTIFICATE => $this->isAbsentEverywhere($enrollment)
                ? $this->result(false, 'Absent à toutes les épreuves de la session.')
                : $this->attendanceAvailability($session, $now),
            self::PAYMENT_CERTIFICATE => $this->paymentAvailability($enrollment),
            default => $this->result(true),
        };
    }

    public function isConvocationAvailableFor(EnrollmentSession $enrollment): bool
    {
        $session = $enrollment->getSession();

        return $session !== null && $this->convocationAvailability($session, $enrollment)['available'];
    }

    private function isAbsentEverywhere(?EnrollmentSession $enrollment): bool
    {
        if ($enrollment === null || $enrollment->getEnrollmentExams()->isEmpty()) {
            return false;
        }

        foreach ($enrollment->getEnrollmentExams() as $enrollmentExam) {
            if ($enrollmentExam->getStatus() !== EnrollmentExamStatusEnum::ABSENT) {
                return false;
            }
        }

        return true;
    }

    private function convocationAvailability(Session $session, ?EnrollmentSession $enrollment): array
    {
        if ($session->getStatus() !== SessionStatusEnum::VALIDATED) {
            return $this->result(false, "Disponible une fois la session validée par l'institut.");
        }

        if ($enrollment !== null && $this->hasUnpaidInvoice($enrollment)) {
            return $this->result(false, "Disponible une fois la facture d'inscription payée.");
        }

        return $this->result(true);
    }

    /**
     * Une facture d'inscription émise mais non réglée bloque la convocation.
     * (Une inscription sans facture — saisie manuelle, gratuité — n'est pas bloquée.)
     */
    public function hasUnpaidInvoice(EnrollmentSession $enrollment): bool
    {
        foreach ($enrollment->getInvoices() as $invoice) {
            if ($invoice->getInvoiceType() === InvoiceTypeEnum::INVOICE
                && $invoice->getStatus() === InvoiceStatusEnum::ISSUED
                && $invoice->getTotalTTC() > 0
            ) {
                return true;
            }
        }

        return false;
    }

    private function attendanceAvailability(Session $session, \DateTimeInterface $now): array
    {
        // Fin de la dernière épreuve : heure de début + durée de l'épreuve
        $lastStart = null;
        foreach ($session->getScheduledExams() as $scheduledExam) {
            $start = $scheduledExam->getStartDate();
            if (!$start) {
                continue;
            }
            $end = \DateTimeImmutable::createFromInterface($start)
                ->modify(sprintf('+%d minutes', $scheduledExam->getExam()?->getDuration() ?? 0));
            if ($lastStart === null || $end > $lastStart) {
                $lastStart = $end;
            }
        }

        if ($lastStart === null) {
            return $this->result(false, 'Aucune épreuve planifiée pour cette session.');
        }

        if ($now > $lastStart) {
            return $this->result(true);
        }

        return $this->result(false, 'Disponible après la dernière épreuve.', $lastStart);
    }

    private function paymentAvailability(?EnrollmentSession $enrollment): array
    {
        if ($enrollment === null) {
            // Calcul pour la session seule : dépend de chaque inscription
            return $this->result(false, 'Disponible pour les inscriptions dont la facture est payée.');
        }

        foreach ($enrollment->getInvoices() as $invoice) {
            if ($invoice->getInvoiceType() === InvoiceTypeEnum::INVOICE
                && $invoice->getStatus() === InvoiceStatusEnum::PAID
            ) {
                return $this->result(true);
            }
        }

        return $this->result(false, "Disponible une fois la facture d'inscription payée.");
    }

    private function result(bool $available, ?string $reason = null, ?\DateTimeInterface $availableFrom = null): array
    {
        return [
            'available' => $available,
            'reason' => $available ? null : $reason,
            'availableFrom' => $availableFrom?->format(\DateTimeInterface::ATOM),
        ];
    }
}
