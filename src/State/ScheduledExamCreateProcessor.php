<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Embeddable\Address;
use App\Entity\ScheduledExam;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use App\Enum\SessionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ScheduledExamCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ScheduledExam
    {
        /** @var ScheduledExam $scheduledExam */
        $scheduledExam = $data;

        $sessionId = $uriVariables['sessionId'] ?? null;
        $session = $this->entityManager->getRepository(Session::class)->find($sessionId);

        if (!$session) {
            throw new NotFoundHttpException('Session introuvable.');
        }

        if (!in_array($session->getStatus(), [SessionStatusEnum::DRAFT, SessionStatusEnum::OPEN])) {
            throw new ConflictHttpException('Les examens planifiés ne peuvent être ajoutés qu\'aux sessions DRAFT ou OPEN.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if (!$this->canCreateScheduledExam($currentUser, $session)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour ajouter un examen planifié à cette session.');
        }

        $exam = $scheduledExam->getExam();
        if ($exam && $session->getAssessment()) {
            if (!$exam->getAssessment()?->getId()?->equals($session->getAssessment()->getId())) {
                throw new UnprocessableEntityHttpException('L\'examen doit appartenir au même assessment que la session.');
            }
        }

        if ($scheduledExam->getStartDate() && $session->getStart() && $session->getEnd()) {
            if ($scheduledExam->getStartDate() < $session->getStart() || $scheduledExam->getStartDate() > $session->getEnd()) {
                throw new UnprocessableEntityHttpException('La date de début de l\'examen doit être dans la plage de la session.');
            }
        }

        $scheduledExam->setSession($session);

        // Centre d'examen : doit appartenir au même institut ; copie adresse + salle
        $examCenter = $scheduledExam->getExamCenter();
        if ($examCenter) {
            if (!$examCenter->getInstitute()?->getId()?->equals($session->getInstitute()?->getId())) {
                throw new UnprocessableEntityHttpException("Le centre d'examen doit appartenir à l'institut de la session.");
            }
            $scheduledExam->setAddress(self::copyAddress($examCenter->getAddress()));
            if (!$scheduledExam->getRoom()) {
                $scheduledExam->setRoom($examCenter->getLabel());
            }
        } elseif (self::isAddressEmpty($scheduledExam->getAddress()) && $session->getInstitute()) {
            $scheduledExam->setAddress(self::copyAddress($session->getInstitute()->getAddress()));
        }

        $this->entityManager->persist($scheduledExam);
        $this->entityManager->flush();

        return $scheduledExam;
    }

    public static function isAddressEmpty(Address $address): bool
    {
        return !$address->getAddress1() && !$address->getAddress2() && !$address->getZipcode()
            && !$address->getCity() && !$address->getCountryCode();
    }

    public static function copyAddress(Address $source): Address
    {
        $copy = new Address();
        $copy->setAddress1($source->getAddress1());
        $copy->setAddress2($source->getAddress2());
        $copy->setZipcode($source->getZipcode());
        $copy->setCity($source->getCity());
        $copy->setCountryCode($source->getCountryCode());

        return $copy;
    }

    private function canCreateScheduledExam(User $user, Session $session): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        $institute = $session->getInstitute();
        if (!$institute) {
            return false;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && in_array($membership->getRole(), [InstituteRoleEnum::ADMIN, InstituteRoleEnum::STAFF], true)
                && $membership->isActive()
            ) {
                return true;
            }
        }

        return false;
    }
}
