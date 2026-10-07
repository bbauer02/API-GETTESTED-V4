<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\DocumentTemplate;
use App\Entity\Institute;
use App\Entity\InstituteMembership;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Repository\DocumentTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

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

        $this->entityManager->persist($institute);

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        $membership = new InstituteMembership();
        $membership->setInstitute($institute);
        $membership->setUser($currentUser);
        $membership->setRole(InstituteRoleEnum::ADMIN);
        $membership->setSince(new \DateTime());

        $this->entityManager->persist($membership);

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
