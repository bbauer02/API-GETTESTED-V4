<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\DocumentTemplate;
use App\Entity\EnrollmentSession;
use App\Enum\EnrollmentStatusEnum;
use App\Entity\Institute;
use App\Entity\User;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentTemplateCollectionProvider implements ProviderInterface
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

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        if (!$this->canView($currentUser, $institute)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour voir les modeles.');
        }

        $repo = $this->entityManager->getRepository(DocumentTemplate::class);

        return $repo->findBy(['institute' => $institute]);
    }

    private function canView(User $user, Institute $institute): bool
    {
        // Platform admin can view all
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        // Institute member (any role) can view
        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())) {
                return true;
            }
        }

        // Candidate enrolled in a session of this institute can view (read-only)
        $enrollments = $this->entityManager->getRepository(EnrollmentSession::class)
            ->createQueryBuilder('es')
            ->join('es.session', 's')
            ->where('es.user = :user')
            ->andWhere('s.institute = :institute')
            ->andWhere('es.status = :active')
            ->setParameter('active', EnrollmentStatusEnum::ACTIVE)
            ->setParameter('user', $user)
            ->setParameter('institute', $institute)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        if (!empty($enrollments)) {
            return true;
        }

        return false;
    }
}
