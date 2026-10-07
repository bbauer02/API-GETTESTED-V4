<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ExamLevelBelongsToAssessment extends Constraint
{
    public string $message = 'Le niveau "{{ level }}" n\'appartient pas à l\'assessment "{{ assessment }}" ni à son parent.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
