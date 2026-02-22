<?php

namespace App\Security\Voter;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\InvoiceStatusEnum;
use App\Enum\PlatformRoleEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class InvoiceVoter extends Voter
{
    public const INVOICE_VIEW = 'INVOICE_VIEW';
    public const INVOICE_VIEW_LIST = 'INVOICE_VIEW_LIST';
    public const INVOICE_EDIT = 'INVOICE_EDIT';
    public const INVOICE_ISSUE = 'INVOICE_ISSUE';
    public const INVOICE_LINE_EDIT = 'INVOICE_LINE_EDIT';
    public const INVOICE_LINE_DELETE = 'INVOICE_LINE_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (in_array($attribute, [self::INVOICE_LINE_EDIT, self::INVOICE_LINE_DELETE])) {
            return $subject instanceof InvoiceLine;
        }

        return in_array($attribute, [
                self::INVOICE_VIEW,
                self::INVOICE_VIEW_LIST,
                self::INVOICE_EDIT,
                self::INVOICE_ISSUE,
            ])
            && $subject instanceof Invoice;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::INVOICE_VIEW => $this->canView($user, $subject),
            self::INVOICE_VIEW_LIST => $this->canViewList($user, $subject),
            self::INVOICE_EDIT => $this->canEdit($user, $subject),
            self::INVOICE_ISSUE => $this->canIssue($user, $subject),
            self::INVOICE_LINE_EDIT, self::INVOICE_LINE_DELETE => $this->canEditLine($user, $subject),
            default => false,
        };
    }

    private function canView(User $user, Invoice $invoice): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        if ($this->isInstituteAdminOrStaff($user, $invoice)) {
            return true;
        }

        // Le propriétaire de l'enrollment lié peut voir sa facture
        $enrollment = $invoice->getEnrollmentSession();
        if ($enrollment && $enrollment->getUser()?->getId()?->equals($user->getId())) {
            return true;
        }

        return false;
    }

    private function canViewList(User $user, Invoice $invoice): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        return $this->isInstituteAdminOrStaff($user, $invoice);
    }

    private function canEdit(User $user, Invoice $invoice): bool
    {
        if ($invoice->getStatus() !== InvoiceStatusEnum::DRAFT) {
            return false;
        }

        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        return $this->isInstituteAdmin($user, $invoice);
    }

    private function canIssue(User $user, Invoice $invoice): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        return $this->isInstituteAdmin($user, $invoice);
    }

    private function canEditLine(User $user, InvoiceLine $line): bool
    {
        $invoice = $line->getInvoice();
        if (!$invoice || $invoice->getStatus() !== InvoiceStatusEnum::DRAFT) {
            return false;
        }

        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        return $this->isInstituteAdmin($user, $invoice);
    }

    private function isPlatformAdmin(User $user): bool
    {
        return $user->getPlatformRole() === PlatformRoleEnum::ADMIN;
    }

    private function isInstituteAdminOrStaff(User $user, Invoice $invoice): bool
    {
        $institute = $invoice->getInstitute();
        if (!$institute) {
            return false;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && in_array($membership->getRole(), [InstituteRoleEnum::ADMIN, InstituteRoleEnum::STAFF])
            ) {
                return true;
            }
        }

        return false;
    }

    private function isInstituteAdmin(User $user, Invoice $invoice): bool
    {
        $institute = $invoice->getInstitute();
        if (!$institute) {
            return false;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && $membership->getRole() === InstituteRoleEnum::ADMIN
            ) {
                return true;
            }
        }

        return false;
    }
}
