<?php

namespace App\Validator;

use App\Entity\Exam;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ExamLevelBelongsToAssessmentValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ExamLevelBelongsToAssessment) {
            throw new UnexpectedTypeException($constraint, ExamLevelBelongsToAssessment::class);
        }

        if (!$value instanceof Exam) {
            throw new UnexpectedValueException($value, Exam::class);
        }

        $level = $value->getLevel();

        // No level assigned → nothing to validate
        if (null === $level) {
            return;
        }

        $assessment = $value->getAssessment();

        if (null === $assessment) {
            return;
        }

        // Collect valid levels: own levels + parent's levels
        $validLevels = $assessment->getLevels()->toArray();

        $parent = $assessment->getParent();
        if (null !== $parent) {
            $validLevels = array_merge($validLevels, $parent->getLevels()->toArray());
        }

        // Check if the exam's level is in the valid set
        foreach ($validLevels as $validLevel) {
            if ($validLevel->getId()?->equals($level->getId())) {
                return;
            }
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ level }}', $level->getLabel() ?? (string) $level->getId())
            ->setParameter('{{ assessment }}', $assessment->getLabel() ?? (string) $assessment->getId())
            ->atPath('level')
            ->addViolation();
    }
}
