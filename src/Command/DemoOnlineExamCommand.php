<?php

namespace App\Command;

use App\Entity\Choice;
use App\Entity\EnrollmentExam;
use App\Entity\EnrollmentSession;
use App\Entity\Institute;
use App\Entity\MCQQuestion;
use App\Entity\ScheduledExam;
use App\Entity\Session;
use App\Entity\Subject;
use App\Entity\SubjectQuestion;
use App\Entity\User;
use App\Enum\EnrollmentExamStatusEnum;
use App\Enum\QuestionStatusEnum;
use App\Enum\SessionStatusEnum;
use App\Enum\SubjectStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Données de démonstration (environnement de dev) : une épreuve en ligne ouverte maintenant,
 * avec un sujet verrouillé de 3 QCM, et un candidat inscrit.
 *
 *   php bin/console app:demo:online-exam --candidate=cLefebre@gmail.com
 */
#[AsCommand(
    name: 'app:demo:online-exam',
    description: 'Crée une épreuve en ligne de démonstration ouverte maintenant, avec un candidat inscrit',
)]
class DemoOnlineExamCommand extends Command
{
    private const QUESTIONS = [
        ['Quelle est la capitale de la France ?', ['Paris' => 1, 'Lyon' => 0, 'Marseille' => 0]],
        ['Choisissez la forme correcte : « Nous ___ au cinéma hier. »', ['sommes allés' => 1, 'avons allé' => 0, 'allons' => 0]],
        ['Quel mot est un synonyme de « rapide » ?', ['vif' => 1, 'lent' => 0, 'lourd' => 0]],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $appEnv,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('candidate', null, InputOption::VALUE_REQUIRED, 'Email du candidat à inscrire', 'cLefebre@gmail.com')
            ->addOption('institute', null, InputOption::VALUE_REQUIRED, 'Nom de l\'institut organisateur', 'Institut Français');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->appEnv === 'prod') {
            $io->error('Commande réservée aux environnements de développement et de recette.');

            return Command::FAILURE;
        }

        $candidate = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $input->getOption('candidate')]);
        $institute = $this->entityManager->getRepository(Institute::class)->findOneBy(['label' => $input->getOption('institute')]);
        if (!$candidate || !$institute) {
            $io->error('Candidat ou institut introuvable.');

            return Command::FAILURE;
        }

        // Modèle : une session existante de l'institut (test, niveau, épreuve)
        $template = null;
        foreach ($institute->getSessions() as $session) {
            if ($session->getLevel() && !$session->getScheduledExams()->isEmpty()) {
                $template = $session;
                break;
            }
        }
        if (!$template) {
            $io->error('L\'institut n\'a aucune session avec niveau et épreuve planifiée à utiliser comme modèle.');

            return Command::FAILURE;
        }

        // L'épreuve modèle est réutilisée telle quelle (sa durée fixe le chronomètre)
        $templateExam = $template->getScheduledExams()->first()->getExam();
        $duration = $templateExam->getDuration() ?? 0;
        $now = new \DateTime();

        $session = (new Session())
            ->setInstitute($institute)
            ->setAssessment($template->getAssessment())
            ->setLevel($template->getLevel())
            ->setStart((clone $now)->modify('-5 minutes'))
            ->setEnd((clone $now)->modify('+2 hours'))
            ->setLimitDateSubscribe((clone $now)->modify('-10 minutes'))
            ->setPlacesAvailable(10)
            ->setStatus(SessionStatusEnum::VALIDATED);
        $this->entityManager->persist($session);

        $scheduledExam = (new ScheduledExam())
            ->setSession($session)
            ->setExam($templateExam)
            ->setStartDate((clone $now)->modify('-1 minute'))
            ->setRoom('Démo en ligne');
        $scheduledExam->setAddress(clone $institute->getAddress());
        $this->entityManager->persist($scheduledExam);

        $subject = (new Subject())
            ->setTitre('Démo — épreuve en ligne')
            ->setScheduledExam($scheduledExam)
            ->setPassingScore(2)
            ->setTotalMaxPoints(count(self::QUESTIONS))
            ->setStatus(SubjectStatusEnum::LOCKED)
            ->setLockedAt(new \DateTimeImmutable());
        $this->entityManager->persist($subject);

        foreach (self::QUESTIONS as $index => [$text, $choices]) {
            $question = (new MCQQuestion())
                ->setLabel(sprintf('Démo %d', $index + 1))
                ->setText($text)
                ->setAssessment($template->getAssessment())
                ->setLevel($template->getLevel())
                ->setMaxPoints(1)
                ->setStatus(QuestionStatusEnum::CALIBRATED);
            $position = 0;
            foreach ($choices as $choiceText => $weight) {
                $choice = (new Choice())->setText($choiceText)->setWeight($weight)->setPosition($position++);
                $question->addChoice($choice);
                $this->entityManager->persist($choice);
            }
            $this->entityManager->persist($question);

            $subjectQuestion = (new SubjectQuestion())
                ->setSubject($subject)
                ->setQuestion($question)
                ->setPosition($index + 1);
            $subject->addSubjectQuestion($subjectQuestion);
            $this->entityManager->persist($subjectQuestion);
        }

        $enrollment = (new EnrollmentSession())
            ->setSession($session)
            ->setUser($candidate)
            ->setRegistrationDate(new \DateTime());
        $this->entityManager->persist($enrollment);

        $enrollmentExam = (new EnrollmentExam())
            ->setEnrollmentSession($enrollment)
            ->setScheduledExam($scheduledExam)
            ->setStatus(EnrollmentExamStatusEnum::REGISTERED);
        $this->entityManager->persist($enrollmentExam);

        $this->entityManager->flush();

        $io->success([
            sprintf('Épreuve en ligne « %s » ouverte maintenant (%s).', $templateExam->getLabel(), $duration ? $duration . ' min' : 'sans limite de temps'),
            sprintf('Candidat : %s — inscription %s', $candidate->getEmail(), $enrollment->getId()),
            sprintf('Épreuve : /dashboard/exam-session/%s', $enrollmentExam->getId()),
        ]);

        return Command::SUCCESS;
    }
}
