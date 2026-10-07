<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\MembershipInviteInput;
use App\Entity\Institute;
use App\Entity\InstituteMembership;
use App\Entity\User;
use App\Enum\CivilityEnum;
use App\Enum\InstituteRoleEnum;
use App\Enum\MembershipStatusEnum;
use App\Enum\PlatformRoleEnum;
use App\Exception\ConflictHttpException;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

/**
 * POST /api/institutes/{instituteId}/memberships/invite
 *
 * Invite un membre par email : si le User existe → membership directe,
 * sinon création d'un compte inactif + email d'invitation (lien set-password).
 */
class MembershipEmailInviteProcessor implements ProcessorInterface
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

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InstituteMembership
    {
        /** @var MembershipInviteInput $input */
        $input = $data;

        $institute = $this->entityManager->getRepository(Institute::class)->find($uriVariables['instituteId'] ?? null);
        if (!$institute) {
            throw new NotFoundHttpException('Institut introuvable.');
        }

        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if (!$this->canManageMembers($currentUser, $institute)) {
            throw new AccessDeniedHttpException("Vous n'avez pas les droits pour gérer les membres de cet institut.");
        }

        $role = InstituteRoleEnum::tryFrom((string) $input->role);
        if (!$role || $role === InstituteRoleEnum::CUSTOMER) {
            throw new UnprocessableEntityHttpException('Le rôle doit être ADMIN, TEACHER ou STAFF.');
        }

        $email = mb_strtolower(trim((string) $input->email));

        $membership = new InstituteMembership();
        $membership->setInstitute($institute);
        $membership->setRole($role);
        $membership->setSince(new \DateTime());

        // Recherche insensible à la casse (les emails des fixtures ne sont pas normalisés)
        $user = $this->entityManager->getRepository(User::class)->createQueryBuilder('u')
            ->where('LOWER(u.email) = :email')
            ->setParameter('email', $email)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($user) {
            $existing = $this->entityManager->getRepository(InstituteMembership::class)->findOneBy([
                'user' => $user,
                'institute' => $institute,
            ]);
            if ($existing !== null) {
                throw new ConflictHttpException('Cet utilisateur est déjà membre de cet institut.');
            }

            $membership->setUser($user);
            $membership->setStatus($user->isActive() ? MembershipStatusEnum::ACTIVE : MembershipStatusEnum::PENDING);

            $this->entityManager->persist($membership);
            $this->entityManager->flush();

            return $membership;
        }

        // Nouveau compte : inactif tant que le mot de passe n'a pas été défini via l'invitation
        $user = new User();
        $user->setEmail($email);
        $user->setFirstname(trim((string) $input->firstname));
        $user->setLastname(trim((string) $input->lastname));
        $user->setCivility(CivilityEnum::M);
        $user->setPlatformRole(PlatformRoleEnum::USER);
        $user->setIsActive(false);
        $user->setIsVerified(false);
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(24))));

        $membership->setUser($user);
        $membership->setStatus(MembershipStatusEnum::PENDING);

        $this->entityManager->persist($user);
        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        $this->sendInvitationEmail($user, $institute, $currentUser);

        return $membership;
    }

    private function sendInvitationEmail(User $user, Institute $institute, User $inviter): void
    {
        $token = $this->tokenService->generateInvitationToken($user);
        $link = rtrim($this->frontendUrl, '/') . '/auth/jwt/set-password/?token=' . $token;

        $html = $this->twig->render('email/invitation.html.twig', [
            'user' => $user,
            'institute' => $institute,
            'inviter' => $inviter,
            'link' => $link,
        ]);

        $email = (new Email())
            ->from('noreply@gettested.fr')
            ->to($user->getEmail())
            ->subject(sprintf('Invitation à rejoindre %s sur GETTESTED', $institute->getLabel()))
            ->html($html);

        $this->mailer->send($email);
    }

    private function canManageMembers(User $user, Institute $institute): bool
    {
        if ($user->getPlatformRole() === PlatformRoleEnum::ADMIN) {
            return true;
        }

        foreach ($institute->getMemberships() as $membership) {
            if ($membership->getUser()?->getId()?->equals($user->getId())
                && $membership->getRole() === InstituteRoleEnum::ADMIN
                && $membership->isActive()
            ) {
                return true;
            }
        }

        return false;
    }
}
