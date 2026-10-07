<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ExamCenter;
use App\Entity\Institute;
use App\Entity\User;
use App\Security\Voter\ExamCenterVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /api/institutes/{instituteId}/exam-centers
 */
class InstituteExamCenterCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly ExamCenterPersistProcessor $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ExamCenter
    {
        /** @var ExamCenter $examCenter */
        $examCenter = $data;

        $institute = $this->entityManager->getRepository(Institute::class)->find($uriVariables['instituteId'] ?? null);
        if (!$institute) {
            throw new NotFoundHttpException('Institut introuvable.');
        }

        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User || !ExamCenterVoter::canEdit($currentUser, $institute)) {
            throw new AccessDeniedHttpException("Vous n'avez pas les droits pour gérer les centres d'examen de cet institut.");
        }

        $examCenter->setInstitute($institute);
        $this->entityManager->persist($examCenter);

        return $this->persistProcessor->process($examCenter, $operation, $uriVariables, $context);
    }
}
