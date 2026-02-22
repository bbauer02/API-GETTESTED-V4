<?php

namespace App\Security\Voter;

use App\Entity\Payment;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PaymentVoter extends Voter
{
    public const PAYMENT_VIEW = 'PAYMENT_VIEW';
    public const PAYMENT_CREATE = 'PAYMENT_CREATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::PAYMENT_VIEW, self::PAYMENT_CREATE])
            && $subject instanceof Payment;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Payment $payment */
        $payment = $subject;

        return match ($attribute) {
            self::PAYMENT_VIEW => $this->canView($user, $payment),
            self::PAYMENT_CREATE => $this->canCreate($user, $payment),
            default => false,
        };
    }

    private function canView(User $user, Payment $payment): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        $invoice = $payment->getInvoice();
        if (!$invoice) {
            return false;
        }

        // Institute ADMIN/STAFF
        $institute = $invoice->getInstitute();
        if ($institute) {
            foreach ($institute->getMemberships() as $membership) {
                if ($membership->getUser()?->getId()?->equals($user->getId())
                    && in_array($membership->getRole(), [InstituteRoleEnum::ADMIN, InstituteRoleEnum::STAFF])
                ) {
                    return true;
                }
            }
        }

        // Propriétaire de l'enrollment
        $enrollment = $invoice->getEnrollmentSession();
        if ($enrollment && $enrollment->getUser()?->getId()?->equals($user->getId())) {
            return true;
        }

        return false;
    }

    private function canCreate(User $user, Payment $payment): bool
    {
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        $invoice = $payment->getInvoice();
        if (!$invoice) {
            return false;
        }

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

    private function isPlatformAdmin(User $user): bool
    {
        return $user->getPlatformRole() === PlatformRoleEnum::ADMIN;
    }
}
