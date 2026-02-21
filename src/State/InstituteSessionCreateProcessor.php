<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Institute;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\PlatformRoleEnum;
use App\Enum\SessionValidationEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InstituteSessionCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Session
    {
        /** @var Session $session */
        $session = $data;

        if (!$session->getStart() || !$session->getEnd()) {
            throw new UnprocessableEntityHttpException('Les dates de début et fin sont requises.');
        }

        if (!$session->getAssessment()) {
            throw new UnprocessableEntityHttpException('L\'assessment est requis.');
        }

        $instituteId = $uriVariables['instituteId'] ?? null;
        $institute = $this->entityManager->getRepository(Institute::class)->find($instituteId);

        if (!$institute) {
            throw new NotFoundHttpException('Institut introuvable.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if (!$this->canCreateSession($currentUser, $institute)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les droits pour créer une session dans cet institut.');
        }

        $session->setInstitute($institute);
        $session->setValidation(SessionValidationEnum::DRAFT);

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }

    private function canCreateSession(User $user, Institute $institute): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && $membership->getRole() === InstituteRoleEnum::ADMIN
            ) {
                return true;
            }
        }

        return false;
    }
}
