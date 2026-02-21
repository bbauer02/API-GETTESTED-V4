<?php

namespace App\DataFixtures;

use App\Entity\Level;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class LevelFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $levels = [
            ['label' => 'A1 - Débutant', 'ref' => 'A1', 'description' => 'Niveau débutant'],
            ['label' => 'A2 - Élémentaire', 'ref' => 'A2', 'description' => 'Niveau élémentaire'],
            ['label' => 'B1 - Intermédiaire', 'ref' => 'B1', 'description' => 'Niveau intermédiaire'],
            ['label' => 'B2 - Intermédiaire avancé', 'ref' => 'B2', 'description' => 'Niveau intermédiaire avancé'],
            ['label' => 'N3 - JLPT Niveau 3', 'ref' => 'N3', 'description' => 'Niveau JLPT N3'],
        ];

        foreach ($levels as $data) {
            $level = new Level();
            $level->setLabel($data['label']);
            $level->setRef($data['ref']);
            $level->setDescription($data['description']);
            $manager->persist($level);
            $this->addReference('level_' . $data['ref'], $level);
        }

        $manager->flush();
    }
}
