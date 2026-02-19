<?php

namespace App\DataFixtures;

use App\Entity\Language;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class LanguageFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['reference'];
    }

    public function load(ObjectManager $manager): void
    {
        $jsonPath = __DIR__ . '/../../data/languages.json';
        $languages = json_decode(file_get_contents($jsonPath), true);

        foreach ($languages as $data) {
            $language = new Language();
            $language->setCode($data['code']);
            $language->setNameOriginal($data['nameOriginal']);
            $language->setNameEn($data['nameEn']);
            $language->setNameFr($data['nameFr']);

            $manager->persist($language);
            $this->addReference('language_' . $data['code'], $language);
        }

        $manager->flush();
    }
}
