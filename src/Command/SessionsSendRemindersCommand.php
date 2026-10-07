<?php

namespace App\Command;

use App\Entity\Session;
use App\Enum\SessionStatusEnum;
use App\Service\CandidateNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rappel envoyé aux candidats N jours avant le début de leur session (par défaut 2).
 * À lancer une fois par jour (cron), par exemple à 9 h :
 *   0 9 * * * php bin/console app:sessions:send-reminders
 * Une session est rappelée le jour calendaire J-N (heure de Paris) : une exécution
 * quotidienne envoie donc un seul rappel par inscription.
 */
#[AsCommand(
    name: 'app:sessions:send-reminders',
    description: 'Envoie un email de rappel aux candidats des sessions qui commencent dans N jours',
)]
class SessionsSendRemindersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CandidateNotifier $candidateNotifier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Nombre de jours avant le début de la session', 2)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les envois sans les effectuer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(0, (int) $input->getOption('days'));
        $dryRun = (bool) $input->getOption('dry-run');

        // Journée cible en heure de Paris, convertie en bornes UTC (stockage en base)
        $paris = new \DateTimeZone('Europe/Paris');
        $dayStart = (new \DateTimeImmutable('today', $paris))->modify(sprintf('+%d days', $days));
        $dayEnd = $dayStart->modify('+1 day');
        $utc = new \DateTimeZone('UTC');

        /** @var Session[] $sessions */
        $sessions = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Session::class, 's')
            ->where('s.status IN (:statuses)')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.start >= :from AND s.start < :to')
            ->setParameter('statuses', [SessionStatusEnum::LOCKED, SessionStatusEnum::VALIDATED])
            ->setParameter('from', \DateTime::createFromImmutable($dayStart->setTimezone($utc)))
            ->setParameter('to', \DateTime::createFromImmutable($dayEnd->setTimezone($utc)))
            ->getQuery()
            ->getResult();

        $sent = 0;
        foreach ($sessions as $session) {
            foreach ($session->getEnrollments() as $enrollment) {
                $io->writeln(sprintf(
                    '%s rappel → %s (session %s du %s)',
                    $dryRun ? '[dry-run]' : '',
                    $enrollment->getUser()?->getEmail(),
                    $session->getAssessment()?->getLabel(),
                    $session->getStart()?->format('d/m/Y'),
                ));

                if (!$dryRun) {
                    $this->candidateNotifier->sessionReminder($enrollment);
                }
                $sent++;
            }
        }

        $io->success(sprintf('%d rappel(s) %s pour le %s.', $sent, $dryRun ? 'à envoyer' : 'envoyé(s)', $dayStart->format('d/m/Y')));

        return Command::SUCCESS;
    }
}
