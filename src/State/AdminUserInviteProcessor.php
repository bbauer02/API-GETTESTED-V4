<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\AdminUserInviteInput;
use App\Entity\User;
use App\Enum\CivilityEnum;
use App\Enum\PlatformRoleEnum;
use App\Exception\ConflictHttpException;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

/**
 * POST /api/admin/users/invite (ROLE_PLATFORM_ADMIN)
 *
 * Crée un compte sans mot de passe utilisable, inactif jusqu'à la définition du mot de passe,
 * et envoie un email d'invitation (lien /auth/jwt/set-password, même jeton que l'invitation d'équipe).
 */
class AdminUserInviteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenService $tokenService,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $frontendUrl,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        /** @var AdminUserInviteInput $input */
        $input = $data;

        $email = mb_strtolower(trim((string) $input->email));

        // Recherche insensible à la casse, y compris les comptes supprimés (soft delete) :
        // la contrainte d'unicité en base porte sur toutes les lignes.
        $filters = $this->entityManager->getFilters();
        $softDeleteEnabled = $filters->isEnabled('soft_delete');
        if ($softDeleteEnabled) {
            $filters->disable('soft_delete');
        }
        try {
            $existing = $this->entityManager->getRepository(User::class)->createQueryBuilder('u')
                ->where('LOWER(u.email) = :email')
                ->setParameter('email', $email)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } finally {
            if ($softDeleteEnabled) {
                $filters->enable('soft_delete');
            }
        }

        if ($existing !== null) {
            throw new ConflictHttpException('Un compte existe déjà avec cette adresse email.');
        }

        $role = PlatformRoleEnum::from((string) $input->platformRole);

        $user = new User();
        $user->setEmail($email);
        $user->setFirstname(trim((string) $input->firstname));
        $user->setLastname(trim((string) $input->lastname));
        $user->setCivility(CivilityEnum::M);
        $user->setPlatformRole($role);
        $user->setIsActive(false);
        $user->setIsVerified(false);
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(24))));

        $phone = trim((string) $input->phone);
        if ($phone !== '') {
            $user->setPhone($phone);
            $user->setPhoneCountryCode($input->phoneCountryCode ?: '+33');
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        /** @var User $inviter */
        $inviter = $this->security->getUser();
        $this->sendInvitationEmail($user, $inviter);

        return $user;
    }

    private function sendInvitationEmail(User $user, User $inviter): void
    {
        $token = $this->tokenService->generateInvitationToken($user);
        $link = rtrim($this->frontendUrl, '/') . '/auth/jwt/set-password/?token=' . $token;

        $html = $this->twig->render('email/user_invitation.html.twig', [
            'user' => $user,
            'inviter' => $inviter,
            'link' => $link,
            'isAdmin' => $user->getPlatformRole() === PlatformRoleEnum::ADMIN,
        ]);

        $this->mailer->send((new Email())
            ->to($user->getEmail())
            ->subject('Votre compte GETTESTED a été créé')
            ->html($html));
    }
}
