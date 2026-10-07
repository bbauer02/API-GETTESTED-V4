<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\DocumentTemplate;
use App\Entity\Institute;
use App\Entity\InstituteMembership;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\InstituteStatusEnum;
use App\Enum\PlatformRoleEnum;
use App\Repository\DocumentTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * POST /api/institutes
 *
 * - Utilisateur ordinaire : l'institut est créé en attente de validation (PENDING_REVIEW)
 *   et le créateur en devient administrateur.
 * - Admin plateforme : l'institut est directement ACTIVE et l'admin plateforme n'en devient
 *   PAS membre ; l'administrateur de l'institut est ensuite désigné par invitation
 *   (POST /api/institutes/{id}/memberships/invite, rôle ADMIN).
 */
class InstituteCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly DocumentTemplateRepository $documentTemplateRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Institute
    {
        /** @var Institute $institute */
        $institute = $data;

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $isPlatformAdmin = $currentUser->getPlatformRole() === PlatformRoleEnum::ADMIN;

        $institute->setStatus($isPlatformAdmin ? InstituteStatusEnum::ACTIVE : InstituteStatusEnum::PENDING_REVIEW);

        $this->entityManager->persist($institute);

        if (!$isPlatformAdmin) {
            $membership = new InstituteMembership();
            $membership->setInstitute($institute);
            $membership->setUser($currentUser);
            $membership->setRole(InstituteRoleEnum::ADMIN);
            $membership->setSince(new \DateTime());

            $this->entityManager->persist($membership);
        }

        // Copy master templates (institute_id = NULL) to the new institute
        $masterTemplates = $this->documentTemplateRepository->findBy(['institute' => null]);

        foreach ($masterTemplates as $master) {
            $copy = new DocumentTemplate();
            $copy->setLabel($master->getLabel());
            $copy->setDocumentType($master->getDocumentType());
            $copy->setContent($master->getContent());
            $copy->setInstitute($institute);
            $copy->setIsDefault(false);

            $this->entityManager->persist($copy);
        }

        $this->entityManager->flush();

        return $institute;
    }
}
