<?php

namespace App\DataFixtures;

use App\Entity\Country;
use App\Entity\Language;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CountryFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public static function getGroups(): array
    {
        return ['reference'];
    }

    public function getDependencies(): array
    {
        return [LanguageFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $countriesJson = __DIR__ . '/../../data/countries.json';
        $countries = json_decode(file_get_contents($countriesJson), true);

        $countryLanguagesJson = __DIR__ . '/../../data/country_languages.json';
        $countryLanguages = json_decode(file_get_contents($countryLanguagesJson), true);

        foreach ($countries as $data) {
            $country = new Country();
            $country->setCode($data['code']);
            $country->setAlpha3($data['alpha3']);
            $country->setNameOriginal($data['nameOriginal']);
            $country->setNameEn($data['nameEn']);
            $country->setNameFr($data['nameFr']);
            $country->setFlag($data['flag'] ?? null);
            $country->setDemonymFr($data['demonymFr'] ?? null);
            $country->setDemonymEn($data['demonymEn'] ?? null);

            // Relations ManyToMany avec les langues
            $langCodes = $countryLanguages[$data['code']] ?? [];
            foreach ($langCodes as $langCode) {
                if ($this->hasReference('language_' . $langCode, Language::class)) {
                    $language = $this->getReference('language_' . $langCode, Language::class);
                    $country->addSpokenLanguage($language);
                }
            }

            $manager->persist($country);
            $this->addReference('country_' . $data['code'], $country);
        }

        $manager->flush();
    }
}
