<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Assessment;
use App\Entity\AssessmentOwnership;
use App\Entity\Exam;
use App\Entity\Question;
use App\Entity\Session;
use App\Exception\ConflictHttpException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Suppression d'un test, refusée (409 avec un message clair) tant que des épreuves, des sessions,
 * des questions ou des sous-tests y sont rattachés. Les liens de propriété des instituts sont supprimés avec lui.
 */
class AssessmentDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var Assessment $assessment */
        $assessment = $data;

        $usage = array_filter([
            'épreuve(s)' => $this->count(Exam::class, $assessment),
            'session(s)' => $this->count(Session::class, $assessment),
            'question(s)' => $this->count(Question::class, $assessment),
            'sous-test(s)' => $assessment->getChildren()->count(),
        ]);

        if ($usage) {
            throw new ConflictHttpException(sprintf(
                'Impossible de supprimer le test « %s » : il comporte encore %s. Supprimez-les d\'abord.',
                $assessment->getLabel(),
                implode(', ', array_map(static fn ($label, $count) => $count . ' ' . $label, array_keys($usage), $usage)),
            ));
        }

        try {
            foreach ($this->entityManager->getRepository(AssessmentOwnership::class)->findBy(['assessment' => $assessment]) as $ownership) {
                $this->entityManager->remove($ownership);
            }
            $this->entityManager->remove($assessment);
            $this->entityManager->flush();
        } catch (ForeignKeyConstraintViolationException) {
            throw new ConflictHttpException(sprintf(
                'Impossible de supprimer le test « %s » : des données y sont encore rattachées (estimations de niveau, entraînements, règles de composition…).',
                $assessment->getLabel(),
            ));
        }
    }

    private function count(string $class, Assessment $assessment): int
    {
        return $this->entityManager->getRepository($class)->count(['assessment' => $assessment]);
    }
}
