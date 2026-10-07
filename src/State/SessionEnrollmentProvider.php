<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\EnrollmentSession;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\EnrollmentStatusEnum;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SessionEnrollmentProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $sessionId = $uriVariables['sessionId'] ?? null;
        $session = $this->entityManager->getRepository(Session::class)->find($sessionId);

        if (!$session) {
            throw new NotFoundHttpException('Session introuvable.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        // platformAdmin ou institute ADMIN/STAFF : voit tous les enrollments
        if ($this->canViewAll($currentUser, $session)) {
            // Inscrits avec candidat, épreuves (et leur planification) et factures en une requête
            return $this->entityManager->getRepository(EnrollmentSession::class)->createQueryBuilder('e')
                ->addSelect('u', 'ee', 'se', 'ex', 'inv')
                ->join('e.user', 'u')
                ->leftJoin('e.enrollmentExams', 'ee')
                ->leftJoin('ee.scheduledExam', 'se')
                ->leftJoin('se.exam', 'ex')
                ->leftJoin('e.invoices', 'inv')
                ->where('e.session = :session')
                ->andWhere('e.status = :active')
                ->setParameter('session', $session)
                ->setParameter('active', EnrollmentStatusEnum::ACTIVE)
                ->orderBy('e.registrationDate', 'ASC')
                ->getQuery()
                ->getResult();
        }

        // user standard : ne voit que son propre enrollment
        $enrollment = $this->entityManager->getRepository(EnrollmentSession::class)
            ->findBy(['session' => $session, 'user' => $currentUser]);

        return $enrollment;
    }

    private function canViewAll(User $user, Session $session): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        $institute = $session->getInstitute();
        if (!$institute) {
            return false;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && $membership->isActive()
                && in_array($membership->getRole(), [InstituteRoleEnum::ADMIN, InstituteRoleEnum::STAFF])
            ) {
                return true;
            }
        }

        return false;
    }
}
