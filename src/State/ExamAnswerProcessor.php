<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\CandidateResponse;
use App\Entity\EnrollmentExam;
use App\Entity\SubjectQuestion;
use App\Enum\EnrollmentExamStatusEnum;
use App\Service\ExamAccessService;
use App\Service\ResponseGraderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ExamAnswerProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly ResponseGraderService $graderService,
        private readonly ExamAccessService $examAccessService,
        private readonly RequestStack $requestStack,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $enrollmentExamId = $uriVariables['id'] ?? null;
        $enrollmentExam = $this->entityManager->getRepository(EnrollmentExam::class)->find($enrollmentExamId);

        if (!$enrollmentExam) {
            throw new NotFoundHttpException('EnrollmentExam introuvable.');
        }

        // Candidat, épreuve démarrée et temps non écoulé
        $this->examAccessService->assertCanAnswer($enrollmentExam, $this->security->getUser());

        // Extract answer data from request
        // Expected: {"subjectQuestionId": "uuid", "givenAnswer": {...}, "responseTimeMs": 12345}
        // deserialize: false : le corps de la requête est lu tel quel
        try {
            $requestData = $this->requestStack->getCurrentRequest()?->toArray() ?? [];
        } catch (\Symfony\Component\HttpFoundation\Exception\JsonException) {
            throw new UnprocessableEntityHttpException('Corps de requête JSON invalide.');
        }
        $subjectQuestionId = $requestData['subjectQuestionId'] ?? null;
        $givenAnswer = $requestData['givenAnswer'] ?? [];
        $responseTimeMs = $requestData['responseTimeMs'] ?? 0;

        if (!$subjectQuestionId) {
            throw new UnprocessableEntityHttpException('subjectQuestionId est requis.');
        }

        // Find SubjectQuestion
        $subjectQuestion = $this->entityManager->getRepository(SubjectQuestion::class)->find($subjectQuestionId);
        if (!$subjectQuestion) {
            throw new NotFoundHttpException('SubjectQuestion introuvable.');
        }

        // La question doit appartenir au sujet de cette épreuve
        $examSubject = $enrollmentExam->getScheduledExam()?->getSubject();
        if (!$examSubject || !$subjectQuestion->getSubject()?->getId()?->equals($examSubject->getId())) {
            throw new UnprocessableEntityHttpException('Cette question ne fait pas partie de votre épreuve.');
        }

        $question = $subjectQuestion->getQuestion();

        // Check for existing response (idempotent: update if exists)
        $existingResponse = $this->entityManager->getRepository(CandidateResponse::class)->findOneBy([
            'enrollmentExam' => $enrollmentExam,
            'subjectQuestion' => $subjectQuestion,
        ]);

        if ($existingResponse) {
            // Update existing
            $existingResponse->setGivenAnswer($givenAnswer);
            $existingResponse->setResponseTimeMs($responseTimeMs);
            $existingResponse->setAnsweredAt(new \DateTime());
            $this->graderService->grade($existingResponse, $question, $subjectQuestion);
            $this->entityManager->flush();

            return [
                'responseId' => $existingResponse->getId()->toRfc4122(),
                'status' => 'updated',
                'acknowledged' => true,
            ];
        }

        // Create new response
        $response = new CandidateResponse();
        $response->setQuestion($question);
        $response->setSubjectQuestion($subjectQuestion);
        $response->setEnrollmentExam($enrollmentExam);
        $response->setGivenAnswer($givenAnswer);
        $response->setResponseTimeMs($responseTimeMs);
        $response->setAnsweredAt(new \DateTime());

        // Grade it
        $this->graderService->grade($response, $question, $subjectQuestion);

        $this->entityManager->persist($response);
        $this->entityManager->flush();

        return [
            'responseId' => $response->getId()->toRfc4122(),
            'status' => 'created',
            'acknowledged' => true,
            // NOTE: we do NOT return the score or correct answer during the exam
        ];
    }
}
