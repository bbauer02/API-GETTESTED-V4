<?php

namespace App\DataFixtures;

use App\Entity\Country;
use App\Entity\Embeddable\Address;
use App\Entity\Language;
use App\Entity\User;
use App\Enum\CivilityEnum;
use App\Enum\GenderEnum;
use App\Enum\PlatformRoleEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements DependentFixtureInterface
{
    public const ADMIN_EMAIL = 'bbauer02@gmail.com';
    public const USER1_EMAIL = 'umebosi1014@yahoo.co.jp';
    public const USER2_EMAIL = 'cLefebre@gmail.com';
    public const INACTIVE_EMAIL = 'didierMoulard@gmail.com';
    public const DEFAULT_PASSWORD = 'password123';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function getDependencies(): array
    {
        return [CountryFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        // Baptiste Bauer - Admin, vérifié, actif
        $admin = $this->createUser(
            email: self::ADMIN_EMAIL,
            firstname: 'Baptiste',
            lastname: 'Bauer',
            civility: CivilityEnum::M,
            gender: GenderEnum::MASCULIN,
            platformRole: PlatformRoleEnum::ADMIN,
            isVerified: true,
            isActive: true,
            phone: '+330323522248',
            phoneCountryCode: '+33',
            address1: '30 rue Robert Leroux',
            zipcode: '02000',
            city: 'Laon',
            countryCode: 'FR',
            birthday: new \DateTime('1982-08-04'),
            nationalityCode: 'FR',
            firstlanguageCode: 'fr',
        );
        $manager->persist($admin);
        $this->addReference('user_admin', $admin);

        // Ayaka Oyama - User, vérifiée, active (nationalité japonaise)
        $user1 = $this->createUser(
            email: self::USER1_EMAIL,
            firstname: 'Ayaka',
            lastname: 'Oyama',
            civility: CivilityEnum::MME,
            gender: GenderEnum::FEMININ,
            platformRole: PlatformRoleEnum::USER,
            isVerified: true,
            isActive: true,
            phone: '0706050403',
            phoneCountryCode: '+33',
            address1: '3 rue Georges Citerne',
            address2: 'Apt 1',
            zipcode: '75015',
            city: 'Paris',
            countryCode: 'FR',
            birthday: new \DateTime('1988-10-14'),
            nationalityCode: 'JP',
            firstlanguageCode: 'ja',
        );
        $manager->persist($user1);
        $this->addReference('user_user1', $user1);

        // Christophe Lefebre - User, non vérifié, actif
        $user2 = $this->createUser(
            email: self::USER2_EMAIL,
            firstname: 'Christophe',
            lastname: 'Lefebre',
            civility: CivilityEnum::M,
            gender: GenderEnum::MASCULIN,
            platformRole: PlatformRoleEnum::USER,
            isVerified: false,
            isActive: true,
            phone: '123456789',
            phoneCountryCode: '+33',
            address1: '5 boulevard Voltaire',
            address2: 'Apt 6',
            zipcode: '13000',
            city: 'Marseille',
            countryCode: 'FR',
            birthday: new \DateTime('1987-11-14'),
            nationalityCode: 'FR',
            firstlanguageCode: 'en',
        );
        $manager->persist($user2);
        $this->addReference('user_user2', $user2);

        // Didier Moulard - User, vérifié, inactif
        $inactive = $this->createUser(
            email: self::INACTIVE_EMAIL,
            firstname: 'Didier',
            lastname: 'Moulard',
            civility: CivilityEnum::M,
            gender: GenderEnum::MASCULIN,
            platformRole: PlatformRoleEnum::USER,
            isVerified: true,
            isActive: false,
            phone: '1234567899',
            phoneCountryCode: '+33',
            address1: '2 rue du Louvres',
            zipcode: '51100',
            city: 'Reims',
            countryCode: 'FR',
            birthday: new \DateTime('1987-11-14'),
            nationalityCode: 'FR',
            firstlanguageCode: 'en',
        );
        $manager->persist($inactive);
        $this->addReference('user_inactive', $inactive);

        $manager->flush();
    }

    private function createUser(
        string $email,
        string $firstname,
        string $lastname,
        CivilityEnum $civility,
        GenderEnum $gender,
        PlatformRoleEnum $platformRole,
        bool $isVerified,
        bool $isActive,
        ?string $phone = null,
        ?string $phoneCountryCode = null,
        ?string $address1 = null,
        ?string $address2 = null,
        ?string $zipcode = null,
        ?string $city = null,
        ?string $countryCode = null,
        ?\DateTimeInterface $birthday = null,
        ?string $nationalityCode = null,
        ?string $firstlanguageCode = null,
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstname($firstname);
        $user->setLastname($lastname);
        $user->setCivility($civility);
        $user->setGender($gender);
        $user->setPlatformRole($platformRole);
        $user->setIsVerified($isVerified);
        $user->setIsActive($isActive);

        if ($phone !== null) {
            $user->setPhone($phone);
        }
        if ($phoneCountryCode !== null) {
            $user->setPhoneCountryCode($phoneCountryCode);
        }

        $address = new Address();
        if ($address1 !== null) {
            $address->setAddress1($address1);
        }
        if ($address2 !== null) {
            $address->setAddress2($address2);
        }
        if ($zipcode !== null) {
            $address->setZipcode($zipcode);
        }
        if ($city !== null) {
            $address->setCity($city);
        }
        if ($countryCode !== null) {
            $address->setCountryCode($countryCode);
        }
        $user->setAddress($address);

        if ($birthday !== null) {
            $user->setBirthday($birthday);
        }

        if ($nationalityCode !== null) {
            $user->setNationality($this->getReference('country_' . $nationalityCode, Country::class));
        }

        if ($firstlanguageCode !== null) {
            $user->setFirstlanguage($this->getReference('language_' . $firstlanguageCode, Language::class));
        }

        if ($countryCode !== null) {
            $user->setNativeCountry($this->getReference('country_' . $countryCode, Country::class));
        }

        if ($isVerified) {
            $user->setEmailVerifiedAt(new \DateTime());
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD);
        $user->setPassword($hashedPassword);

        return $user;
    }
}
