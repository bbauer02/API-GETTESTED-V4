<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\EnrollmentSession;
use App\Entity\User;
use App\Enum\PlatformRoleEnum;
use App\Enum\SessionValidationEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class EnrollmentCancelProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var EnrollmentSession $enrollment */
        $enrollment = $data;

        $session = $enrollment->getSession();
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $isAdmin = $currentUser->getPlatformRole() === PlatformRoleEnum::ADMIN;

        // Vérifier que la session est OPEN (ou DRAFT pour admin)
        $validation = $session?->getValidation();
        if ($validation !== SessionValidationEnum::OPEN && !($isAdmin && $validation === SessionValidationEnum::DRAFT)) {
            throw new ConflictHttpException('Impossible d\'annuler l\'inscription : la session n\'est pas ouverte.');
        }

        // Vérifier la date limite (sauf admin)
        $limitDate = $session?->getLimitDateSubscribe();
        if (!$isAdmin && $limitDate !== null && new \DateTime() > $limitDate) {
            throw new UnprocessableEntityHttpException('La date limite d\'inscription est dépassée.');
        }

        // Supprimer les EnrollmentExam associés
        foreach ($enrollment->getEnrollmentExams() as $enrollmentExam) {
            $this->entityManager->remove($enrollmentExam);
        }

        // Supprimer l'EnrollmentSession
        $this->entityManager->remove($enrollment);
        $this->entityManager->flush();

        return null;
    }
}
