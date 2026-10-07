<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Institute;
use App\Enum\InstituteRoleEnum;
use App\Enum\InstituteStatusEnum;
use App\Enum\MembershipStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * PATCH /api/institutes/{id}/status (ROLE_PLATFORM_ADMIN)
 *
 * Valide, suspend ou réactive un institut. Quand l'institut devient ACTIVE,
 * ses administrateurs sont prévenus par email.
 */
class InstituteStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $frontendUrl,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Institute
    {
        /** @var Institute $institute */
        $institute = $data;

        /** @var Institute|null $previous */
        $previous = $context['previous_data'] ?? null;
        $previousStatus = $previous?->getStatus();

        $this->entityManager->flush();

        if ($institute->getStatus() === InstituteStatusEnum::ACTIVE
            && $previousStatus !== null
            && $previousStatus !== InstituteStatusEnum::ACTIVE
        ) {
            $this->notifyAdmins($institute, $previousStatus === InstituteStatusEnum::SUSPENDED);
        }

        return $institute;
    }

    private function notifyAdmins(Institute $institute, bool $reactivated): void
    {
        $recipients = [];
        foreach ($institute->getMemberships() as $membership) {
            $user = $membership->getUser();
            if ($user
                && $membership->getRole() === InstituteRoleEnum::ADMIN
                && $membership->getStatus() !== MembershipStatusEnum::ARCHIVED
            ) {
                $recipients[mb_strtolower($user->getEmail())] = $user->getFirstname();
            }
        }

        // Aucun administrateur rattaché : on écrit à l'adresse de contact de l'institut
        if (!$recipients && $institute->getEmail()) {
            $recipients[mb_strtolower($institute->getEmail())] = null;
        }

        $link = rtrim($this->frontendUrl, '/') . '/dashboard/espace-institut/fiche';

        foreach ($recipients as $email => $firstname) {
            try {
                $html = $this->twig->render('email/institute_validated.html.twig', [
                    'institute' => $institute,
                    'firstname' => $firstname,
                    'reactivated' => $reactivated,
                    'link' => $link,
                ]);

                $this->mailer->send((new Email())
                    ->to($email)
                    ->subject($reactivated
                        ? sprintf('Votre institut %s a été réactivé sur GETTESTED', $institute->getLabel())
                        : sprintf('Votre institut %s a été validé sur GETTESTED', $institute->getLabel()))
                    ->html($html));
            } catch (\Throwable $e) {
                // L'échec d'envoi ne doit pas annuler le changement de statut
                $this->logger->error('Email de validation d\'institut non envoyé : ' . $e->getMessage(), [
                    'institute' => (string) $institute->getId(),
                    'email' => $email,
                ]);
            }
        }
    }
}
