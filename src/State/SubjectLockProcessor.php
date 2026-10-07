<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Subject;
use App\Entity\User;
use App\Enum\SubjectStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SubjectLockProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Subject
    {
        /** @var Subject $subject */
        $subject = $data;

        if ($subject->getStatus() !== SubjectStatusEnum::DRAFT) {
            throw new UnprocessableEntityHttpException('Seul un sujet en statut DRAFT peut être verrouillé.');
        }

        // Must have at least 1 non-seed question
        $nonSeedQuestions = $subject->getSubjectQuestions()->filter(
            fn($sq) => !$sq->isSeed()
        );

        if ($nonSeedQuestions->isEmpty()) {
            throw new UnprocessableEntityHttpException('Le sujet doit contenir au moins une question non-seed.');
        }

        // Calculate totalMaxPoints
        $totalMaxPoints = 0.0;
        foreach ($subject->getSubjectQuestions() as $sq) {
            if (!$sq->isSeed()) {
                $points = $sq->getPointsOverride() ?? $sq->getQuestion()->getMaxPoints();
                $totalMaxPoints += $points;
            }
        }

        $subject->setTotalMaxPoints($totalMaxPoints);

        // Validate passingScore
        if ($subject->getPassingScore() === null) {
            throw new UnprocessableEntityHttpException('Le score de passage (passingScore) doit être défini avant le verrouillage.');
        }

        if ($subject->getPassingScore() > $totalMaxPoints) {
            throw new UnprocessableEntityHttpException(
                sprintf('Le score de passage (%.1f) ne peut pas dépasser le total des points (%.1f).', $subject->getPassingScore(), $totalMaxPoints)
            );
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        $subject->setStatus(SubjectStatusEnum::LOCKED);
        $subject->setLockedAt(new \DateTimeImmutable());
        $subject->setLockedBy($currentUser);

        $this->entityManager->flush();

        return $subject;
    }
}
