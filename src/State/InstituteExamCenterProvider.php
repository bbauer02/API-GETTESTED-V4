<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ExamCenter;
use App\Entity\Institute;
use App\Entity\User;
use App\Security\Voter\ExamCenterVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InstituteExamCenterProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $institute = $this->entityManager->getRepository(Institute::class)->find($uriVariables['instituteId'] ?? null);
        if (!$institute) {
            throw new NotFoundHttpException('Institut introuvable.');
        }

        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User || !ExamCenterVoter::canView($currentUser, $institute)) {
            throw new AccessDeniedHttpException("Vous n'avez pas les droits pour voir les centres d'examen de cet institut.");
        }

        return $this->entityManager->getRepository(ExamCenter::class)->findBy(
            ['institute' => $institute],
            ['isDefault' => 'DESC', 'label' => 'ASC']
        );
    }
}
