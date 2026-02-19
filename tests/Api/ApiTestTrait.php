<?php

namespace App\Tests\Api;

use App\DataFixtures\CountryFixtures;
use App\DataFixtures\LanguageFixtures;
use App\DataFixtures\UserFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
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

        $fixtureLoader = new Loader();
        $fixtureLoader->addFixture($container->get(LanguageFixtures::class));
        $fixtureLoader->addFixture($container->get(CountryFixtures::class));
        $fixtureLoader->addFixture($container->get(UserFixtures::class));

        $purger = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);
        $executor->execute($fixtureLoader->getFixtures());

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
