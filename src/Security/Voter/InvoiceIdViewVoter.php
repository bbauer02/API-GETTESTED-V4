<?php

namespace App\Security\Voter;

use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Uid\Uuid;

/**
 * Sous-ressources d'une facture (/invoices/{invoiceId}/lines, /payments) : mêmes droits que la facture.
 */
class InvoiceIdViewVoter extends Voter
{
    public const INVOICE_VIEW_ID = 'INVOICE_VIEW_ID';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::INVOICE_VIEW_ID && is_string($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $invoice = Uuid::isValid($subject) ? $this->entityManager->getRepository(Invoice::class)->find($subject) : null;

        return $invoice !== null && $this->accessDecisionManager->decide($token, ['INVOICE_VIEW'], $invoice);
    }
}
