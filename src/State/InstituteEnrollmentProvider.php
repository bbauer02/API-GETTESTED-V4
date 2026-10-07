<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\EnrollmentSession;
use App\Entity\Institute;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /api/institutes/{instituteId}/enrollments — toutes les inscriptions des sessions de l'institut
 * (admin plateforme ou membre actif ADMIN/STAFF/TEACHER). Filtre optionnel ?session={sessionId}.
 */
class InstituteEnrollmentProvider implements ProviderInterface
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
        if (!$currentUser instanceof User || !$this->canView($currentUser, $institute)) {
            throw new AccessDeniedHttpException("Vous n'avez pas les droits pour voir les inscriptions de cet institut.");
        }

        // Inscriptions avec candidat, session, épreuves et factures en une requête
        $qb = $this->entityManager->getRepository(EnrollmentSession::class)->createQueryBuilder('e')
            ->addSelect('u', 's', 'a', 'l', 'ee', 'se', 'ex', 'inv', 'inst', 'sse', 'sex', 'ssubj', 'pub', 'dt')
            ->join('e.user', 'u')
            ->join('e.session', 's')
            ->leftJoin('s.assessment', 'a')
            ->leftJoin('s.level', 'l')
            ->leftJoin('e.enrollmentExams', 'ee')
            ->leftJoin('ee.scheduledExam', 'se')
            ->leftJoin('se.exam', 'ex')
            ->leftJoin('e.invoices', 'inv')
            ->leftJoin('s.institute', 'inst')
            ->leftJoin('s.scheduledExams', 'sse')
            ->leftJoin('sse.exam', 'sex')
            ->leftJoin('sse.subject', 'ssubj')
            ->leftJoin('s.documentPublications', 'pub')
            ->leftJoin('pub.documentType', 'dt')
            ->where('s.institute = :institute')
            ->setParameter('institute', $institute)
            ->orderBy('e.registrationDate', 'DESC');

        $sessionId = $context['filters']['session'] ?? null;
        if ($sessionId) {
            $qb->andWhere('s.id = :sessionId')->setParameter('sessionId', $sessionId);
        }

        return $qb->getQuery()->getResult();
    }

    private function canView(User $user, Institute $institute): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && $membership->isActive()
                && in_array($membership->getRole(), [InstituteRoleEnum::ADMIN, InstituteRoleEnum::STAFF, InstituteRoleEnum::TEACHER], true)
            ) {
                return true;
            }
        }

        return false;
    }
}
