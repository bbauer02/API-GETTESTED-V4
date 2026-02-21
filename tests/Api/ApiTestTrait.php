<?php

namespace App\Tests\Api;

use App\DataFixtures\AssessmentFixtures;
use App\DataFixtures\CountryFixtures;
use App\DataFixtures\ExamFixtures;
use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\LanguageFixtures;
use App\DataFixtures\LevelFixtures;
use App\DataFixtures\SessionFixtures;
use App\DataFixtures\SkillFixtures;
use App\DataFixtures\UserFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;

trait ApiTestTrait
{
    private function loadFixtures(): void
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        // Disable soft delete filter during fixture loading
        $em->getFilters()->disable('soft_delete');

        $fixtures = [
            $container->get(LanguageFixtures::class),
            $container->get(CountryFixtures::class),
            $container->get(UserFixtures::class),
            $container->get(InstituteFixtures::class),
            $container->get(LevelFixtures::class),
            $container->get(SkillFixtures::class),
            $container->get(AssessmentFixtures::class),
            $container->get(ExamFixtures::class),
            $container->get(SessionFixtures::class),
        ];

        $purger = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);
        $executor->execute($fixtures);

        // Re-enable soft delete filter
        $em->getFilters()->enable('soft_delete');
    }

    private function getJwtToken(string $email, string $password): string
    {
        $client = static::getClient();
        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => $email,
            'password' => $password,
        ]));

        $data = json_decode($client->getResponse()->getContent(), true);
        return $data['token'];
    }
}
