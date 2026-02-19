<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;

class UserMeProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?User
    {
        error_log('UserMeProvider::provide called for operation: ' . $operation->getName());
        $securityUser = $this->security->getUser();
        error_log('Security user: ' . ($securityUser ? get_class($securityUser) . '=' . $securityUser->getUserIdentifier() : 'null'));

        if ($securityUser instanceof User) {
            $user = $this->userRepository->findOneByEmail($securityUser->getUserIdentifier());
            error_log('Found user: ' . ($user ? $user->getEmail() : 'null'));
            return $user;
        }

        return null;
    }
}
