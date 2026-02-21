<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Enum\PlatformRoleEnum;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

class UserRegistrationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenService $tokenService,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        /** @var User $user */
        $user = $data;

        $hashedPassword = $this->passwordHasher->hashPassword($user, $user->getPassword());
        $user->setPassword($hashedPassword);
        $user->setPlatformRole(PlatformRoleEnum::USER);
        $user->setIsVerified(false);
        $user->setIsActive(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->sendVerificationEmail($user);

        return $user;
    }

    private function sendVerificationEmail(User $user): void
    {
        $token = $this->tokenService->generateVerificationToken($user);

        $html = $this->twig->render('email/verify_email.html.twig', [
            'user' => $user,
            'token' => $token,
        ]);

        $email = (new Email())
            ->from('noreply@gettested.fr')
            ->to($user->getEmail())
            ->subject('Vérification de votre adresse email - GETTESTED')
            ->html($html);

        $this->mailer->send($email);
    }
}
