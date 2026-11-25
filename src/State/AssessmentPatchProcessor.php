<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Assessment;
use App\Entity\User;
use App\Enum\PlatformRoleEnum;
use App\Service\AssessmentIntegrityChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * PATCH /assessments/{id} (sécurité ASSESSMENT_EDIT évaluée sur le test avant modification) :
 * - isInternal n'est modifiable que par l'administrateur plateforme ;
 * - le test parent doit être interne ou lié à l'institut propriétaire, sans cycle ;
 * - un niveau encore utilisé (épreuves, questions, sessions) ne peut pas être retiré.
 */
class AssessmentPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly AssessmentIntegrityChecker $integrityChecker,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Assessment
    {
        /** @var Assessment $assessment */
        $assessment = $data;
        /** @var Assessment|null $previous */
        $previous = $context['previous_data'] ?? null;

        $user = $this->security->getUser();
        $isPlatformAdmin = $user instanceof User && $user->getPlatformRole() === PlatformRoleEnum::ADMIN;

        if (!$isPlatformAdmin && $previous instanceof Assessment && $previous->isInternal() !== $assessment->isInternal()) {
            throw new AccessDeniedHttpException('Seule la plateforme peut rendre un test interne.');
        }

        $previousParentId = $previous?->getParent()?->getId();
        $newParentId = $assessment->getParent()?->getId();
        $parentChanged = ($previousParentId === null) !== ($newParentId === null)
            || ($previousParentId !== null && !$previousParentId->equals($newParentId));

        if ($parentChanged) {
            $this->integrityChecker->assertParentAllowed(
                $assessment,
                $user instanceof User ? $user : null,
                $this->integrityChecker->getOwnerInstitutes($assessment),
            );
        }

        $this->integrityChecker->assertLevelsStillAvailable($assessment);

        $this->entityManager->flush();

        return $assessment;
    }
}
