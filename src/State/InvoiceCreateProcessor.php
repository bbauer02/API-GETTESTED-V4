<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Embeddable\Counterparty;
use App\Entity\Institute;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\User;
use App\Enum\BusinessTypeEnum;
use App\Enum\InstituteRoleEnum;
use App\Enum\InvoiceStatusEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvoiceCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Invoice
    {
        $instituteId = $uriVariables['instituteId'] ?? null;
        $institute = $this->entityManager->getRepository(Institute::class)->find($instituteId);

        if (!$institute) {
            throw new NotFoundHttpException('Institut introuvable.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        if (!$this->canCreate($currentUser, $institute)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour créer une facture.');
        }

        /** @var Invoice $invoice */
        $invoice = $data;
        $invoice->setInstitute($institute);
        $invoice->setStatus(InvoiceStatusEnum::DRAFT);

        // Pré-remplir le seller depuis l'institut
        $seller = new Counterparty();
        $seller->setName($institute->getLabel());
        $seller->setAddress($institute->getAddress()->getAddress1());
        $seller->setCity($institute->getAddress()->getCity());
        $seller->setZipcode($institute->getAddress()->getZipcode());
        $seller->setCountryCode($institute->getAddress()->getCountryCode());
        $seller->setVatNumber($institute->getVatNumber());
        $seller->setSiren($institute->getSiren());
        $seller->setSiret($institute->getSiret());
        $seller->setLegalForm($institute->getLegalForm());
        $seller->setShareCapital($institute->getShareCapital());
        $seller->setRcsCity($institute->getRcsCity());
        $invoice->setSeller($seller);

        // Si businessType non défini, ENROLLMENT par défaut
        if ($invoice->getBusinessType() === null) {
            $invoice->setBusinessType(BusinessTypeEnum::ENROLLMENT);
        }

        // Si enrollmentSession fourni : pré-remplir buyer + créer les lignes
        $enrollment = $invoice->getEnrollmentSession();
        if ($enrollment) {
            $enrolledUser = $enrollment->getUser();
            if ($enrolledUser) {
                $buyer = new Counterparty();
                $buyer->setName($enrolledUser->getFirstname() . ' ' . $enrolledUser->getLastname());
                $buyer->setAddress($enrolledUser->getAddress()->getAddress1());
                $buyer->setCity($enrolledUser->getAddress()->getCity());
                $buyer->setZipcode($enrolledUser->getAddress()->getZipcode());
                $buyer->setCountryCode($enrolledUser->getAddress()->getCountryCode());
                $invoice->setBuyer($buyer);
            }

            // Créer les InvoiceLines depuis les ScheduledExams
            $session = $enrollment->getSession();
            if ($session) {
                foreach ($session->getScheduledExams() as $scheduledExam) {
                    $exam = $scheduledExam->getExam();
                    if (!$exam) {
                        continue;
                    }

                    $line = new InvoiceLine();
                    $line->setLabel($exam->getLabel());
                    $line->setExam($exam);
                    $line->setQuantity(1);

                    // Chercher le prix personnalisé de l'institut
                    $pricing = $scheduledExam->getExamPricing();
                    if ($pricing && $pricing->getPrice()->getAmount() !== null) {
                        $line->setUnitPriceHT($pricing->getPrice()->getAmount());
                        if ($pricing->getPrice()->getTva() !== null) {
                            $line->setTvaRate($pricing->getPrice()->getTva());
                        }
                    } elseif ($exam->getPrice()->getAmount() !== null) {
                        $line->setUnitPriceHT($exam->getPrice()->getAmount());
                        if ($exam->getPrice()->getTva() !== null) {
                            $line->setTvaRate($exam->getPrice()->getTva());
                        }
                    } else {
                        $line->setUnitPriceHT(0);
                    }

                    $line->computeAmounts();
                    $invoice->addLine($line);
                    $this->entityManager->persist($line);
                }
            }
        }

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    private function canCreate(User $user, Institute $institute): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
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
