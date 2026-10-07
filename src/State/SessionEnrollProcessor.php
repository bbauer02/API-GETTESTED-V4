<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\EnrollmentExam;
use App\Entity\EnrollmentSession;
use App\Enum\EnrollmentStatusEnum;
use App\Entity\ScheduledExam;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\EnrollmentExamStatusEnum;
use App\Enum\SessionStatusEnum;
use App\Service\InvoiceService;
use App\Service\CandidateNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Exception\ConflictHttpException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SessionEnrollProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly InvoiceService $invoiceService,
        private readonly RequestStack $requestStack,
        private readonly CandidateNotifier $candidateNotifier,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EnrollmentSession
    {
        $sessionId = $uriVariables['sessionId'] ?? null;
        $session = $this->entityManager->getRepository(Session::class)->find($sessionId);

        if (!$session) {
            throw new NotFoundHttpException('Session introuvable.');
        }

        // 1. Vérifier session OPEN
        if ($session->getStatus() !== SessionStatusEnum::OPEN) {
            throw new ConflictHttpException('La session n\'est pas ouverte aux inscriptions.');
        }

        // 2. Vérifier places disponibles
        $placesAvailable = $session->getPlacesAvailable();
        if ($placesAvailable !== null) {
            $enrollmentCount = $this->entityManager->getRepository(EnrollmentSession::class)
                ->count(['session' => $session, 'status' => EnrollmentStatusEnum::ACTIVE]);
            if ($enrollmentCount >= $placesAvailable) {
                throw new ConflictHttpException('Plus de places disponibles pour cette session.');
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
            ->findOneBy(['session' => $session, 'user' => $currentUser, 'status' => EnrollmentStatusEnum::ACTIVE]);
        if ($existingEnrollment) {
            throw new ConflictHttpException('Vous êtes déjà inscrit à cette session.');
        }

        // 5. Épreuves retenues : toutes les obligatoires + les options cochées par le candidat
        $selectedScheduledExams = $this->resolveSelectedScheduledExams($session);

        // Transaction unique : enrollment + facture. Si quoi que ce soit plante, tout est annulé.
        $conn = $this->entityManager->getConnection();
        $conn->beginTransaction();

        try {
            // 5. Créer l'EnrollmentSession
            $enrollment = new EnrollmentSession();
            $enrollment->setSession($session);
            $enrollment->setUser($currentUser);
            $enrollment->setRegistrationDate(new \DateTime());
            $this->entityManager->persist($enrollment);

            // 6. Créer un EnrollmentExam pour chaque épreuve retenue
            foreach ($selectedScheduledExams as $scheduledExam) {
                $enrollmentExam = new EnrollmentExam();
                $enrollmentExam->setEnrollmentSession($enrollment);
                $enrollmentExam->setScheduledExam($scheduledExam);
                $enrollmentExam->setStatus(EnrollmentExamStatusEnum::REGISTERED);
                $this->entityManager->persist($enrollmentExam);
                $enrollment->getEnrollmentExams()->add($enrollmentExam);
            }

            // 7. Flush l'enrollment avant de créer la facture (dans la même transaction)
            $this->entityManager->flush();

            // 8. Créer et émettre la facture institut → candidat
            $this->invoiceService->createEnrollmentInvoice($enrollment);

            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        // 9. Rafraîchir l'enrollment pour que la collection invoices soit à jour en mémoire
        $this->entityManager->refresh($enrollment);

        $this->candidateNotifier->enrollmentConfirmed($enrollment);

        return $enrollment;
    }

    /**
     * Lit `scheduledExamIds` (UUID ou IRI) dans le corps de la requête.
     * Les épreuves obligatoires sont toujours incluses ; une option n'est retenue que si elle est demandée.
     *
     * @return ScheduledExam[]
     */
    private function resolveSelectedScheduledExams(Session $session): array
    {
        try {
            $payload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];
        } catch (\Symfony\Component\HttpFoundation\Exception\JsonException) {
            $payload = [];
        }
        $requestedIds = array_map(
            static fn ($id) => basename((string) $id),
            (array) ($payload['scheduledExamIds'] ?? [])
        );

        $sessionExamIds = [];
        $selected = [];
        foreach ($session->getScheduledExams() as $scheduledExam) {
            $id = (string) $scheduledExam->getId();
            $sessionExamIds[] = $id;

            $isOption = $scheduledExam->getExam()?->isOption() ?? false;
            if (!$isOption || in_array($id, $requestedIds, true)) {
                $selected[] = $scheduledExam;
            }
        }

        $unknownIds = array_diff($requestedIds, $sessionExamIds);
        if ($unknownIds) {
            throw new UnprocessableEntityHttpException('Certaines épreuves demandées ne font pas partie de cette session.');
        }

        if (!$selected) {
            throw new UnprocessableEntityHttpException('Aucune épreuve sélectionnée pour cette inscription.');
        }

        return $selected;
    }
}
