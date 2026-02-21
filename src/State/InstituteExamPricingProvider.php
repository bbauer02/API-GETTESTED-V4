<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Institute;
use App\Entity\InstituteExamPricing;
use App\Entity\User;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InstituteExamPricingProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $instituteId = $uriVariables['instituteId'] ?? null;
        $institute = $this->entityManager->getRepository(Institute::class)->find($instituteId);

        if (!$institute) {
            throw new NotFoundHttpException('Institut introuvable.');
        }

        $currentUser = $this->security->getUser();

        if (!$currentUser instanceof User || !$this->isMember($currentUser, $institute)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour voir les tarifs de cet institut.');
        }

        return $this->entityManager->getRepository(InstituteExamPricing::class)->findBy([
            'institute' => $institute,
        ]);
    }

    private function isMember(User $user, Institute $institute): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())) {
                return true;
            }
        }

        return false;
    }
}
