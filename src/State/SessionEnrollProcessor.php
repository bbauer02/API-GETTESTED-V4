<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\EnrollmentExam;
use App\Entity\EnrollmentSession;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\EnrollmentExamStatusEnum;
use App\Enum\SessionValidationEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SessionEnrollProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EnrollmentSession
    {
        $sessionId = $uriVariables['sessionId'] ?? null;
        $session = $this->entityManager->getRepository(Session::class)->find($sessionId);

        if (!$session) {
            throw new UnprocessableEntityHttpException('Session introuvable.');
        }

        // 1. Vérifier session OPEN
        if ($session->getValidation() !== SessionValidationEnum::OPEN) {
            throw new UnprocessableEntityHttpException('La session n\'est pas ouverte aux inscriptions.');
        }

        // 2. Vérifier places disponibles
        $placesAvailable = $session->getPlacesAvailable();
        if ($placesAvailable !== null) {
            $enrollmentCount = $this->entityManager->getRepository(EnrollmentSession::class)
                ->count(['session' => $session]);
            if ($enrollmentCount >= $placesAvailable) {
                throw new UnprocessableEntityHttpException('Plus de places disponibles pour cette session.');
            }
        }

        // 3. Vérifier date limite d'inscription
        $limitDate = $session->getLimitDateSubscribe();
        if ($limitDate !== null && new \DateTime() > $limitDate) {
            throw new UnprocessableEntityHttpException('La date limite d\'inscription est dépassée.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        // 4. Vérifier que l'utilisateur n'est pas déjà inscrit
        $existingEnrollment = $this->entityManager->getRepository(EnrollmentSession::class)
            ->findOneBy(['session' => $session, 'user' => $currentUser]);
        if ($existingEnrollment) {
            throw new UnprocessableEntityHttpException('Vous êtes déjà inscrit à cette session.');
        }

        // 5. Créer l'EnrollmentSession
        $enrollment = new EnrollmentSession();
        $enrollment->setSession($session);
        $enrollment->setUser($currentUser);
        $enrollment->setRegistrationDate(new \DateTime());
        $this->entityManager->persist($enrollment);

        // 6. Créer un EnrollmentExam pour chaque ScheduledExam
        foreach ($session->getScheduledExams() as $scheduledExam) {
            $enrollmentExam = new EnrollmentExam();
            $enrollmentExam->setEnrollmentSession($enrollment);
            $enrollmentExam->setScheduledExam($scheduledExam);
            $enrollmentExam->setStatus(EnrollmentExamStatusEnum::REGISTERED);
            $this->entityManager->persist($enrollmentExam);
            $enrollment->getEnrollmentExams()->add($enrollmentExam);
        }

        // 7. Flush
        $this->entityManager->flush();

        return $enrollment;
    }
}
