<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Institute;
use App\Entity\InstituteMembership;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class InstituteCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
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
        $this->entityManager->flush();

        return $institute;
    }
}
