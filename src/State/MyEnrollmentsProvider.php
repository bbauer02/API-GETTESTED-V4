<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\EnrollmentSessionRepository;
use Symfony\Bundle\SecurityBundle\Security;

class MyEnrollmentsProvider implements ProviderInterface
{
    public function __construct(
        private readonly EnrollmentSessionRepository $enrollmentRepository,
        private readonly Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user) {
            return [];
        }

        return $this->enrollmentRepository->findBy(
            ['user' => $user],
            ['registrationDate' => 'DESC']
        );
    }
}
