<?php

namespace App\DataFixtures;

use App\Entity\Assessment;
use App\Entity\Embeddable\Price;
use App\Entity\Exam;
use App\Entity\Institute;
use App\Entity\InstituteExamPricing;
use App\Entity\Level;
use App\Entity\Skill;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ExamFixtures extends Fixture implements DependentFixtureInterface
{
    public const TOEIC_LISTENING_LABEL = 'TOEIC Listening';
    public const TOEIC_READING_LABEL = 'TOEIC Reading';

    public function getDependencies(): array
    {
        return [AssessmentFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var Assessment $toeic */
        $toeic = $this->getReference('assessment_toeic', Assessment::class);
        /** @var Institute $institute1 */
        $institute1 = $this->getReference('institute_1', Institute::class);

        // TOEIC Listening
        $listening = new Exam();
        $listening->setLabel(self::TOEIC_LISTENING_LABEL);
        $listening->setAssessment($toeic);
        $listening->setIsWritten(false);
        $listening->setIsOption(false);
        $listening->setCoeff(1);
        $listening->setNbrQuestions(100);
        $listening->setDuration(45);
        $listening->setSuccessScore(50);
        $listening->addSkill($this->getReference('skill_listening', Skill::class));
        $listening->setLevel($this->getReference('level_B1', Level::class));

        $listeningPrice = new Price();
        $listeningPrice->setAmount(50.0);
        $listeningPrice->setCurrency('EUR');
        $listeningPrice->setTva(20.0);
        $listening->setPrice($listeningPrice);

        $manager->persist($listening);
        $this->addReference('exam_toeic_listening', $listening);

        // TOEIC Reading
        $reading = new Exam();
        $reading->setLabel(self::TOEIC_READING_LABEL);
        $reading->setAssessment($toeic);
        $reading->setIsWritten(true);
        $reading->setIsOption(false);
        $reading->setCoeff(1);
        $reading->setNbrQuestions(100);
        $reading->setDuration(75);
        $reading->setSuccessScore(50);
        $reading->addSkill($this->getReference('skill_reading', Skill::class));
        $reading->setLevel($this->getReference('level_B2', Level::class));

        $readingPrice = new Price();
        $readingPrice->setAmount(50.0);
        $readingPrice->setCurrency('EUR');
        $readingPrice->setTva(20.0);
        $reading->setPrice($readingPrice);

        $manager->persist($reading);
        $this->addReference('exam_toeic_reading', $reading);

        // InstituteExamPricing — Institut Français propose un tarif pour TOEIC Listening
        $pricing = new InstituteExamPricing();
        $pricing->setInstitute($institute1);
        $pricing->setExam($listening);
        $pricing->setIsActive(true);

        $pricingPrice = new Price();
        $pricingPrice->setAmount(65.0);
        $pricingPrice->setCurrency('EUR');
        $pricingPrice->setTva(20.0);
        $pricing->setPrice($pricingPrice);

        $manager->persist($pricing);

        $manager->flush();
    }
}
