<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\InvoiceStatusEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvoiceLineCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InvoiceLine
    {
        $invoiceId = $uriVariables['invoiceId'] ?? null;
        $invoice = $this->entityManager->getRepository(Invoice::class)->find($invoiceId);

        if (!$invoice) {
            throw new NotFoundHttpException('Facture introuvable.');
        }

        if ($invoice->getStatus() !== InvoiceStatusEnum::DRAFT) {
            throw new ConflictHttpException('Impossible d\'ajouter une ligne à une facture qui n\'est pas en brouillon.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        if (!$this->canEdit($currentUser, $invoice)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour modifier cette facture.');
        }

        /** @var InvoiceLine $line */
        $line = $data;
        $line->setInvoice($invoice);
        $line->computeAmounts();

        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $line;
    }

    private function canEdit(User $user, Invoice $invoice): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
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
}
