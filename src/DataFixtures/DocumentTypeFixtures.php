<?php

namespace App\DataFixtures;

use App\Entity\DocumentType;
use App\Service\DocumentAccessService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Types de documents standard (codes utilisés par DocumentAccessService et SessionWorkflowSubscriber).
 */
class DocumentTypeFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach (DocumentAccessService::TYPES as $code => $label) {
            $documentType = new DocumentType();
            $documentType->setCode($code);
            $documentType->setLabel($label);
            $manager->persist($documentType);
            $this->addReference('document_type_' . $code, $documentType);
        }

        $manager->flush();
    }
}
