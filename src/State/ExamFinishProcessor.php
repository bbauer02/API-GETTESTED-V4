<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\EnrollmentExam;
use App\Service\ExamAccessService;
use App\Service\ExamFinisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExamFinishProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ExamAccessService $examAccessService,
        private readonly ExamFinisher $examFinisher,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $enrollmentExamId = $uriVariables['id'] ?? null;
        $enrollmentExam = $this->entityManager->getRepository(EnrollmentExam::class)->find($enrollmentExamId);

        if (!$enrollmentExam) {
            throw new NotFoundHttpException('EnrollmentExam introuvable.');
        }

        // Le candidat peut terminer à tout moment (y compris après l'échéance : soumission automatique)
        $this->examAccessService->assertCandidate($enrollmentExam, $this->security->getUser());

        return $this->examFinisher->finish($enrollmentExam);
    }
}
