<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AbilityEstimate;
use App\Entity\PracticeSession;
use App\Entity\User;
use App\Enum\PracticeSessionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class PracticeStartProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PracticeSession
    {
        /** @var PracticeSession $session */
        $session = $data;

        /** @var User $user */
        $user = $this->security->getUser();
        $session->setUser($user);
        $session->setStatus(PracticeSessionStatusEnum::IN_PROGRESS);
        $session->setStartedAt(new \DateTime());

        // Set initial theta from latest ability estimate if available
        $latestEstimate = $this->entityManager->getRepository(AbilityEstimate::class)->findOneBy(
            ['user' => $user, 'assessment' => $session->getAssessment(), 'level' => $session->getLevel()],
            ['estimatedAt' => 'DESC']
        );

        $session->setInitialTheta($latestEstimate ? $latestEstimate->getTheta() : 0.0);

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }
}
