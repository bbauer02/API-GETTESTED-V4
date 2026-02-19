<?php

namespace App\DataFixtures;

use App\Entity\Embeddable\Address;
use App\Entity\Institute;
use App\Entity\InstituteMembership;
use App\Entity\StripeAccount;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class InstituteFixtures extends Fixture implements DependentFixtureInterface
{
    public const INSTITUTE1_LABEL = 'Institut Français';
    public const INSTITUTE2_LABEL = 'Tenri Japanese School';

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var User $admin */
        $admin = $this->getReference('user_admin', User::class);       // user_id 1 — Baptiste
        /** @var User $user1 */
        $user1 = $this->getReference('user_user1', User::class);       // user_id 2 — Ayaka
        /** @var User $user2 */
        $user2 = $this->getReference('user_user2', User::class);       // user_id 3 — Christophe
        /** @var User $inactive */
        $inactive = $this->getReference('user_inactive', User::class); // user_id 4 — Didier

        // ==============================
        // Institut 1 — Institut Français
        // ==============================
        $institute1 = new Institute();
        $institute1->setLabel(self::INSTITUTE1_LABEL);
        $institute1->setSiteweb('www.institut-france.fr');
        $institute1->setSocialNetworks([
            'facebook' => 'FbProfil',
            'twitter' => 'TwitProfil',
            'instagram' => 'InstaProfil',
            'linkedin' => 'LinkedProfil',
        ]);

        $address1 = new Address();
        $address1->setAddress1('123 rue du Général Degaule');
        $address1->setZipcode('75000');
        $address1->setCity('Paris');
        $address1->setCountryCode('FR');
        $institute1->setAddress($address1);

        $manager->persist($institute1);

        $stripe1 = new StripeAccount();
        $stripe1->setStripeId('acct_1KxRS8QxaIfpu4Sw');
        $stripe1->setIsActivated(true);
        $stripe1->setInstitute($institute1);
        $manager->persist($stripe1);

        $this->addReference('institute_1', $institute1);

        // ===================================
        // Institut 2 — Tenri Japanese School
        // ===================================
        $institute2 = new Institute();
        $institute2->setLabel(self::INSTITUTE2_LABEL);
        $institute2->setSiteweb('www.tenri.co.jp');
        $institute2->setSocialNetworks([
            'facebook' => 'FbProfil',
            'twitter' => 'TwitProfil',
            'instagram' => 'InstaProfil',
            'linkedin' => 'LinkedProfil',
        ]);

        $address2 = new Address();
        $address2->setAddress1('456bis Avenue Jules Brunet');
        $address2->setAddress2('Apt 15B - Bât 12');
        $address2->setZipcode('75000');
        $address2->setCity('Paris');
        $address2->setCountryCode('JP');
        $institute2->setAddress($address2);

        $manager->persist($institute2);

        $stripe2 = new StripeAccount();
        $stripe2->setStripeId('acct_1KxRS8QxaIfpu4Sw');
        $stripe2->setIsActivated(true);
        $stripe2->setInstitute($institute2);
        $manager->persist($stripe2);

        $this->addReference('institute_2', $institute2);

        // ============================================================
        // Memberships — basé sur mock-class.js #fillInstitutHasDefaultUsers
        //
        // Rôles mock : 1=User(CUSTOMER), 2=Teacher, 3=Manager(STAFF), 4=Admin
        //
        // { user_id: 1, institut_id: 2, role_id: 1 } → Baptiste → Tenri, CUSTOMER
        // { user_id: 1, institut_id: 1, role_id: 1 } → Baptiste → Institut Français, CUSTOMER
        // { user_id: 2, institut_id: 1, role_id: 4 } → Ayaka → Institut Français, ADMIN
        // { user_id: 3, institut_id: 2, role_id: 1 } → Christophe → Tenri, CUSTOMER
        // { user_id: 4, institut_id: 2, role_id: 2 } → Didier → Tenri, TEACHER
        // ============================================================

        $memberships = [
            // Baptiste (admin plateforme) → Tenri, CUSTOMER
            ['user' => $admin, 'institute' => $institute2, 'role' => InstituteRoleEnum::CUSTOMER],
            // Baptiste (admin plateforme) → Institut Français, CUSTOMER
            ['user' => $admin, 'institute' => $institute1, 'role' => InstituteRoleEnum::CUSTOMER],
            // Ayaka → Institut Français, ADMIN
            ['user' => $user1, 'institute' => $institute1, 'role' => InstituteRoleEnum::ADMIN],
            // Christophe → Tenri, CUSTOMER
            ['user' => $user2, 'institute' => $institute2, 'role' => InstituteRoleEnum::CUSTOMER],
            // Didier → Tenri, TEACHER
            ['user' => $inactive, 'institute' => $institute2, 'role' => InstituteRoleEnum::TEACHER],
        ];

        foreach ($memberships as $data) {
            $membership = new InstituteMembership();
            $membership->setUser($data['user']);
            $membership->setInstitute($data['institute']);
            $membership->setRole($data['role']);
            $membership->setSince(new \DateTime());
            $manager->persist($membership);
        }

        $manager->flush();
    }
}
