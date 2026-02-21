<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AssessmentOwnership;
use App\Entity\Institute;
use App\Entity\User;
use App\Enum\OwnershipTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InstituteOwnershipCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AssessmentOwnership
    {
        /** @var AssessmentOwnership $ownership */
        $ownership = $data;

        $instituteId = $uriVariables['instituteId'] ?? null;
        $institute = $this->entityManager->getRepository(Institute::class)->find($instituteId);

        if (!$institute) {
            throw new NotFoundHttpException('Institut introuvable.');
        }

        $ownership->setInstitute($institute);
        $ownership->setOwnershipType(OwnershipTypeEnum::BUYER);
        $ownership->setRelationshipDate(new \DateTime());

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $ownership->setUser($currentUser);

        $this->entityManager->persist($ownership);
        $this->entityManager->flush();

        return $ownership;
    }
}
