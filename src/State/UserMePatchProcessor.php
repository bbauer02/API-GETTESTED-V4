<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
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

        // Sauvegarder l'ancien email pour détecter un changement
        $previousEmail = $user->getEmail();

        // Deserialiser le body JSON dans l'entité existante (merge)
        $json = $context['request']->getContent();
        $this->serializer->deserialize($json, User::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $user,
            AbstractNormalizer::GROUPS => $operation->getDenormalizationContext()['groups'] ?? [],
        ]);

        // Si l'email a changé → re-vérification nécessaire
        if ($user->getEmail() !== $previousEmail) {
            $user->setIsVerified(false);
            $user->setEmailVerifiedAt(null);

            $this->entityManager->flush();

            $this->sendVerificationEmail($user);

            return $user;
        }

        $this->entityManager->flush();

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
            ->to($user->getEmail())
            ->subject('Vérification de votre nouvelle adresse email - GETTESTED')
            ->html($html);

        $this->mailer->send($email);
    }
}
