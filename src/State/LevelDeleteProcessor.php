<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Assessment;
use App\Entity\Exam;
use App\Entity\Level;
use App\Entity\Question;
use App\Entity\Session;
use App\Exception\ConflictHttpException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Suppression d'un niveau : refusée tant qu'une épreuve, une question ou une session l'utilise.
 * Le niveau est d'abord détaché des tests qui le référencent.
 */
class LevelDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var Level $level */
        $level = $data;

        $usage = [
            'épreuve(s)' => $this->count(Exam::class, $level),
            'question(s)' => $this->count(Question::class, $level),
            'session(s)' => $this->count(Session::class, $level),
        ];
        $usage = array_filter($usage);

        if ($usage !== []) {
            $parts = [];
            foreach ($usage as $label => $count) {
                $parts[] = $count . ' ' . $label;
            }
            throw new ConflictHttpException(sprintf(
                'Ce niveau est encore utilisé (%s) : il ne peut pas être supprimé.',
                implode(', ', $parts)
            ));
        }

        $assessments = $this->entityManager->getRepository(Assessment::class)
            ->createQueryBuilder('a')
            ->innerJoin('a.levels', 'l')
            ->where('l.id = :levelId')
            ->setParameter('levelId', $level->getId(), 'uuid')
            ->getQuery()
            ->getResult();

        foreach ($assessments as $assessment) {
            $assessment->removeLevel($level);
        }

        $this->entityManager->remove($level);
        $this->entityManager->flush();

        return null;
    }

    private function count(string $class, Level $level): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from($class, 'e')
            ->where('e.level = :level')
            ->setParameter('level', $level->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
