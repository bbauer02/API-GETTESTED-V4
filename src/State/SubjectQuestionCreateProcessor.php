<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Question;
use App\Entity\Subject;
use App\Entity\SubjectQuestion;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use App\Enum\SubjectStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SubjectQuestionCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SubjectQuestion
    {
        /** @var SubjectQuestion $subjectQuestion */
        $subjectQuestion = $data;

        $subjectId = $uriVariables['subjectId'] ?? null;
        $subject = $this->entityManager->getRepository(Subject::class)->find($subjectId);

        if (!$subject) {
            throw new NotFoundHttpException('Sujet introuvable.');
        }

        if ($subject->getStatus() !== SubjectStatusEnum::DRAFT) {
            throw new UnprocessableEntityHttpException('Impossible d\'ajouter des questions à un sujet qui n\'est pas en DRAFT.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if (!$this->canEditSubject($currentUser, $subject)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour modifier ce sujet.');
        }

        $question = $subjectQuestion->getQuestion();
        if (!$question) {
            throw new UnprocessableEntityHttpException('La question est requise.');
        }

        // Reload question to ensure it exists
        $question = $this->entityManager->getRepository(Question::class)->find($question->getId());
        if (!$question) {
            throw new NotFoundHttpException('Question introuvable.');
        }

        // Validate same assessment: the question's assessment must match the session's assessment
        $scheduledExam = $subject->getScheduledExam();
        $session = $scheduledExam?->getSession();
        $sessionAssessment = $session?->getAssessment();
        $questionAssessment = $question->getAssessment();

        if ($sessionAssessment && $questionAssessment
            && !$sessionAssessment->getId()->equals($questionAssessment->getId())) {
            throw new UnprocessableEntityHttpException(
                'La question doit appartenir au même assessment que la session.'
            );
        }

        // Check for duplicate
        foreach ($subject->getSubjectQuestions() as $existingSq) {
            if ($existingSq->getQuestion()->getId()->equals($question->getId())) {
                throw new UnprocessableEntityHttpException('Cette question est déjà dans le sujet.');
            }
        }

        // Auto-set position if not provided
        if ($subjectQuestion->getPosition() === null || $subjectQuestion->getPosition() === 0) {
            $maxPosition = 0;
            foreach ($subject->getSubjectQuestions() as $sq) {
                if ($sq->getPosition() > $maxPosition) {
                    $maxPosition = $sq->getPosition();
                }
            }
            $subjectQuestion->setPosition($maxPosition + 1);
        }

        $subjectQuestion->setSubject($subject);
        $subjectQuestion->setQuestion($question);

        $this->entityManager->persist($subjectQuestion);
        $this->entityManager->flush();

        return $subjectQuestion;
    }

    private function canEditSubject(User $user, Subject $subject): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        $session = $subject->getScheduledExam()?->getSession();
        if (!$session) {
            return false;
        }

        $institute = $session->getInstitute();
        if (!$institute) {
            return false;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && in_array($membership->getRole(), [InstituteRoleEnum::ADMIN, InstituteRoleEnum::TEACHER])
            ) {
                return true;
            }
        }

        return false;
    }
}
