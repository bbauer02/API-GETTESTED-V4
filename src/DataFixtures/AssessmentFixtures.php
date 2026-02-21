<?php

namespace App\DataFixtures;

use App\Entity\Assessment;
use App\Entity\AssessmentOwnership;
use App\Entity\Institute;
use App\Entity\Level;
use App\Entity\Skill;
use App\Entity\User;
use App\Enum\OwnershipTypeEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AssessmentFixtures extends Fixture implements DependentFixtureInterface
{
    public const TOEIC_LABEL = 'TOEIC';
    public const JLPT_LABEL = 'JLPT';
    public const CUSTOM_LABEL = 'Test personnalisé Institut Français';

    public function getDependencies(): array
    {
        return [
            LevelFixtures::class,
            SkillFixtures::class,
            InstituteFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var Institute $institute1 */
        $institute1 = $this->getReference('institute_1', Institute::class);
        /** @var Institute $institute2 */
        $institute2 = $this->getReference('institute_2', Institute::class);
        /** @var User $admin */
        $admin = $this->getReference('user_admin', User::class);

        // TOEIC — test interne (géré par la plateforme)
        $toeic = new Assessment();
        $toeic->setLabel(self::TOEIC_LABEL);
        $toeic->setRef('TOEIC');
        $toeic->setIsInternal(true);
        $toeic->addLevel($this->getReference('level_B1', Level::class));
        $toeic->addLevel($this->getReference('level_B2', Level::class));
        $toeic->addSkill($this->getReference('skill_listening', Skill::class));
        $toeic->addSkill($this->getReference('skill_reading', Skill::class));
        $manager->persist($toeic);
        $this->addReference('assessment_toeic', $toeic);

        // JLPT — test interne
        $jlpt = new Assessment();
        $jlpt->setLabel(self::JLPT_LABEL);
        $jlpt->setRef('JLPT');
        $jlpt->setIsInternal(true);
        $jlpt->addLevel($this->getReference('level_N3', Level::class));
        $jlpt->addSkill($this->getReference('skill_listening', Skill::class));
        $jlpt->addSkill($this->getReference('skill_reading', Skill::class));
        $jlpt->addSkill($this->getReference('skill_grammar', Skill::class));
        $manager->persist($jlpt);
        $this->addReference('assessment_jlpt', $jlpt);

        // Test personnalisé — externe (créé par Institut Français)
        $custom = new Assessment();
        $custom->setLabel(self::CUSTOM_LABEL);
        $custom->setRef('CUSTOM-IF');
        $custom->setIsInternal(false);
        $custom->addLevel($this->getReference('level_A1', Level::class));
        $custom->addLevel($this->getReference('level_A2', Level::class));
        $custom->addSkill($this->getReference('skill_writing', Skill::class));
        $custom->addSkill($this->getReference('skill_reading', Skill::class));
        $manager->persist($custom);
        $this->addReference('assessment_custom', $custom);

        // AssessmentOwnership : Institut Français est OWNER du test personnalisé
        $ownership1 = new AssessmentOwnership();
        $ownership1->setInstitute($institute1);
        $ownership1->setAssessment($custom);
        $ownership1->setOwnershipType(OwnershipTypeEnum::OWNER);
        $ownership1->setRelationshipDate(new \DateTime());
        $ownership1->setUser($admin);
        $manager->persist($ownership1);

        // AssessmentOwnership : Tenri est BUYER du test personnalisé
        $ownership2 = new AssessmentOwnership();
        $ownership2->setInstitute($institute2);
        $ownership2->setAssessment($custom);
        $ownership2->setOwnershipType(OwnershipTypeEnum::BUYER);
        $ownership2->setRelationshipDate(new \DateTime());
        $ownership2->setUser($admin);
        $manager->persist($ownership2);

        $manager->flush();
    }
}
