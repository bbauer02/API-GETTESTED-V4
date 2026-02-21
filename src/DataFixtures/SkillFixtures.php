<?php

namespace App\DataFixtures;

use App\Entity\Skill;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SkillFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $skills = [
            ['label' => 'Listening', 'description' => 'Compréhension orale'],
            ['label' => 'Reading', 'description' => 'Compréhension écrite'],
            ['label' => 'Writing', 'description' => 'Expression écrite'],
            ['label' => 'Grammar', 'description' => 'Grammaire'],
        ];

        foreach ($skills as $data) {
            $skill = new Skill();
            $skill->setLabel($data['label']);
            $skill->setDescription($data['description']);
            $manager->persist($skill);
            $this->addReference('skill_' . strtolower($data['label']), $skill);
        }

        $manager->flush();
    }
}
