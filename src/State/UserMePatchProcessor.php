<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Environment;

class UserMePatchProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly SerializerInterface $serializer,
        private readonly TokenService $tokenService,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        $currentUser = $this->security->getUser();

        if (!$currentUser instanceof User) {
            throw new BadRequestHttpException('Utilisateur non trouvé.');
        }

        // Recharger l'entité managée par Doctrine
        $user = $this->entityManager->getRepository(User::class)->find($currentUser->getId());

        if ($user === null) {
            throw new BadRequestHttpException('Utilisateur non trouvé.');
        }

        // Sauvegarder l'ancien email / téléphone pour détecter un changement
        $previousEmail = $user->getEmail();
        $previousPhone = $user->getPhone();
        $previousPhoneCountryCode = $user->getPhoneCountryCode();

        // Deserialiser le body JSON dans l'entité existante (merge)
        $json = $context['request']->getContent();
        $this->serializer->deserialize($json, User::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $user,
            AbstractNormalizer::GROUPS => $operation->getDenormalizationContext()['groups'] ?? [],
        ]);

        // Si le téléphone a changé → la vérification SMS est invalidée
        if ($user->getPhone() !== $previousPhone || $user->getPhoneCountryCode() !== $previousPhoneCountryCode) {
            $user->setPhoneVerifiedAt(null);
            $user->setPhoneVerificationCode(null);
            $user->setPhoneVerificationExpiresAt(null);
        }

        // Si l'email a changé → mot de passe actuel exigé, puis re-vérification
        if (mb_strtolower((string) $user->getEmail()) !== mb_strtolower((string) $previousEmail)) {
            $body = json_decode($json, true) ?? [];
            $currentPassword = $body['currentPassword'] ?? null;
            if (!is_string($currentPassword) || !$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->entityManager->refresh($user);

                throw new UnprocessableEntityHttpException('Saisissez votre mot de passe actuel pour changer d\'adresse email.');
            }

            $this->notifyPreviousEmail($user, $previousEmail);

            $user->setIsVerified(false);
            $user->setEmailVerifiedAt(null);

            $this->entityManager->flush();

            $this->sendVerificationEmail($user);

            return $user;
        }

        $this->entityManager->flush();

        return $user;
    }

    /** Prévient l'ancienne adresse : un changement non sollicité doit pouvoir être signalé. */
    private function notifyPreviousEmail(User $user, string $previousEmail): void
    {
        $email = (new Email())
            ->to($previousEmail)
            ->subject('Votre adresse email a été modifiée - GETTESTED')
            ->html($this->twig->render('email/email_changed.html.twig', [
                'user' => $user,
                'previousEmail' => $previousEmail,
            ]));

        $this->mailer->send($email);
    }

    private function sendVerificationEmail(User $user): void
    {
        $token = $this->tokenService->generateVerificationToken($user);

        $html = $this->twig->render('email/verify_email.html.twig', [
            'user' => $user,
            'token' => $token,
        ]);

        $email = (new Email())
            ->to($user->getEmail())
            ->subject('Vérification de votre nouvelle adresse email - GETTESTED')
            ->html($html);

        $this->mailer->send($email);
    }
}
