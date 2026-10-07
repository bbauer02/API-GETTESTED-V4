<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Institute;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use App\Security\Voter\SessionVoter;
use App\Service\SessionAutoLockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InstituteSessionProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly SessionAutoLockService $autoLockService,
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

        if (!$currentUser instanceof User || !$this->canViewSessions($currentUser, $institute)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour voir les sessions de cet institut.');
        }

        // Une seule requête pour la session, son test, ses épreuves, leurs sujets et examinateurs
        // (au lieu d'une série de requêtes par session)
        $sessions = $this->entityManager->getRepository(Session::class)->createQueryBuilder('s')
            ->addSelect('a', 'l', 'se', 'e', 'subject', 'examinator', 'center', 'publication', 'documentType')
            ->leftJoin('s.assessment', 'a')
            ->leftJoin('s.level', 'l')
            ->leftJoin('s.scheduledExams', 'se')
            ->leftJoin('se.exam', 'e')
            ->leftJoin('se.subject', 'subject')
            ->leftJoin('se.examinators', 'examinator')
            ->leftJoin('se.examCenter', 'center')
            ->leftJoin('s.documentPublications', 'publication')
            ->leftJoin('publication.documentType', 'documentType')
            ->where('s.institute = :institute')
            ->setParameter('institute', $institute)
            ->orderBy('s.start', 'DESC')
            ->getQuery()
            ->getResult();

        // TEACHER : uniquement les sessions dont il est examinateur
        if (!$this->canViewAllSessions($currentUser, $institute)) {
            $sessions = array_values(array_filter(
                $sessions,
                fn (Session $session) => SessionVoter::isExaminatorOf($currentUser, $session),
            ));
        }

        // Verrouillage automatique paresseux des sessions OPEN expirées
        $this->autoLockService->lockExpiredAmong($sessions);

        return $sessions;
    }

    private function canViewSessions(User $user, Institute $institute): bool
    {
        return $this->canViewAllSessions($user, $institute)
            || $this->hasActiveRole($user, $institute, [InstituteRoleEnum::TEACHER]);
    }

    private function canViewAllSessions(User $user, Institute $institute): bool
    {
        return $user->getPlatformRole() === PlatformRoleEnum::ADMIN
            || $this->hasActiveRole($user, $institute, [InstituteRoleEnum::ADMIN, InstituteRoleEnum::STAFF]);
    }

    private function hasActiveRole(User $user, Institute $institute, array $roles): bool
    {
        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && $membership->isActive()
                && in_array($membership->getRole(), $roles, true)
            ) {
                return true;
            }
        }

        return false;
    }
}
