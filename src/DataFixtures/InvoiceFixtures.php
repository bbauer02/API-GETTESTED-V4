<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InvoiceFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            SessionFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Pas de données pré-chargées — les factures sont créées automatiquement
        // par InvoiceService lors de l'inscription via le flow d'enrollment.
    }
}
