<?php

namespace App\Command;

use App\Entity\EnrollmentExam;
use App\Enum\EnrollmentExamStatusEnum;
use App\Enum\EnrollmentStatusEnum;
use App\Enum\SubjectStatusEnum;
use App\Service\ExamAccessService;
use App\Service\ExamFinisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Clôture des épreuves en ligne restées ouvertes :
 * - démarrée et temps écoulé (candidat parti sans « Terminer ») → notée avec les réponses enregistrées ;
 * - jamais démarrée et créneau terminé → candidat ABSENT.
 *
 * À lancer régulièrement (cron), par exemple toutes les 10 minutes :
 *   *\/10 * * * * php bin/console app:exams:close-expired
 */
#[AsCommand(
    name: 'app:exams:close-expired',
    description: 'Note les épreuves en ligne dont le temps est écoulé et marque absents les candidats qui ne les ont pas commencées',
)]
class ExamsCloseExpiredCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExamAccessService $examAccessService,
        private readonly ExamFinisher $examFinisher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les épreuves concernées sans les modifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $now = new \DateTime();

        /** @var EnrollmentExam[] $openExams */
        $openExams = $this->entityManager->createQueryBuilder()
            ->select('ee', 'se', 'subject')
            ->from(EnrollmentExam::class, 'ee')
            ->join('ee.scheduledExam', 'se')
            ->join('se.subject', 'subject')
            ->join('ee.enrollmentSession', 'es')
            ->where('ee.status = :registered')
            ->andWhere('es.status = :active')
            ->setParameter('active', EnrollmentStatusEnum::ACTIVE)
            ->andWhere('subject.status = :locked')
            ->setParameter('registered', EnrollmentExamStatusEnum::REGISTERED)
            ->setParameter('locked', SubjectStatusEnum::LOCKED)
            ->getQuery()
            ->getResult();

        $finished = $absent = 0;

        foreach ($openExams as $enrollmentExam) {
            $candidate = $enrollmentExam->getEnrollmentSession()?->getUser()?->getEmail();

            if ($enrollmentExam->getStartedAt() !== null) {
                if (!$this->examAccessService->isExpired($enrollmentExam, $now)) {
                    continue;
                }

                $io->writeln(sprintf('%s notée (temps écoulé) : %s', $dryRun ? '[dry-run]' : '', $candidate));
                if (!$dryRun) {
                    $this->examFinisher->finish($enrollmentExam);
                }
                $finished++;
                continue;
            }

            $closesAt = $this->examAccessService->windowClosesAt($enrollmentExam);
            if ($closesAt === null) {
                continue;
            }
            $graceEnd = \DateTimeImmutable::createFromInterface($closesAt)
                ->modify(sprintf('+%d seconds', ExamAccessService::GRACE_SECONDS));
            if ($now <= $graceEnd) {
                continue;
            }

            $io->writeln(sprintf('%s absent (épreuve non commencée) : %s', $dryRun ? '[dry-run]' : '', $candidate));
            if (!$dryRun) {
                $enrollmentExam->setStatus(EnrollmentExamStatusEnum::ABSENT);
                $enrollmentExam->setFinalScore(null);
            }
            $absent++;
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%d épreuve(s) notée(s), %d candidat(s) marqué(s) absent(s)%s.',
            $finished,
            $absent,
            $dryRun ? ' (simulation)' : ''
        ));

        return Command::SUCCESS;
    }
}
