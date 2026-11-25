<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use App\Entity\DocumentTemplate;
use App\Entity\DocumentType;
use App\Entity\Level;
use App\Entity\Skill;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Intégrité du référentiel pédagogique : niveaux modifiés sur place, suppressions refusées (409)
 * pour un niveau, un type de document ou une compétence encore utilisés.
 */
class LevelAndSkillIntegrityTest extends WebTestCase
{
    use ApiTestTrait;
    use AuditTestTrait;

    private function skill(string $label): Skill
    {
        return $this->em()->getRepository(Skill::class)->findOneBy(['label' => $label]);
    }

    // ========================
    // 12. Niveaux
    // ========================

    public function testOwnerAdminPatchesLevelInPlace(): void
    {
        static::createClient();
        $this->loadFixtures();

        // A1 appartient au test personnalisé dont l'Institut Français est propriétaire
        $levelId = $this->level('A1')->getId();
        $levelCount = $this->em()->getRepository(Level::class)->count([]);

        [$status, $data] = $this->api('PATCH', '/api/levels/' . $levelId, $this->token(UserFixtures::USER1_EMAIL), [
            'label' => 'A1 - Grand débutant',
        ]);
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertSame((string) $levelId, $data['id']);
        $this->assertSame('A1 - Grand débutant', $data['label']);

        // Même niveau modifié, aucun niveau créé
        $this->em()->clear();
        $this->assertSame('A1 - Grand débutant', $this->em()->getRepository(Level::class)->find($levelId)->getLabel());
        $this->assertSame($levelCount, $this->em()->getRepository(Level::class)->count([]));
    }

    public function testCandidateCannotPatchLevel(): void
    {
        static::createClient();
        $this->loadFixtures();

        [$status] = $this->api('PATCH', '/api/levels/' . $this->level('A1')->getId(), $this->token(UserFixtures::USER2_EMAIL), [
            'label' => 'Piratage',
        ]);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status);
    }

    public function testDeleteUsedLevelIsConflict(): void
    {
        static::createClient();
        $this->loadFixtures();

        // A1 est utilisé par une épreuve et une session du test personnalisé
        $levelId = $this->level('A1')->getId();

        [$status, $data] = $this->api('DELETE', '/api/levels/' . $levelId, $this->token(UserFixtures::USER1_EMAIL));
        $this->assertSame(Response::HTTP_CONFLICT, $status);
        $this->assertStringContainsString('encore utilisé', json_encode($data, JSON_UNESCAPED_UNICODE));

        $this->em()->clear();
        $this->assertNotNull($this->em()->getRepository(Level::class)->find($levelId));
    }

    public function testDeleteUnusedLevelSucceeds(): void
    {
        static::createClient();
        $this->loadFixtures();

        // B1 n'est utilisé nulle part
        $levelId = $this->level('B1')->getId();

        [$status] = $this->api('DELETE', '/api/levels/' . $levelId, $this->token(UserFixtures::ADMIN_EMAIL));
        $this->assertSame(Response::HTTP_NO_CONTENT, $status);

        $this->em()->clear();
        $this->assertNull($this->em()->getRepository(Level::class)->find($levelId));
    }

    // ========================
    // 13. Types de documents et compétences
    // ========================

    public function testDeleteUsedDocumentTypeIsConflict(): void
    {
        static::createClient();
        $this->loadFixtures();

        $em = $this->em();
        $used = $em->getRepository(DocumentType::class)->findOneBy(['code' => 'CONVOCATION']);
        $template = new DocumentTemplate();
        $template->setLabel('Convocation maître');
        $template->setDocumentType($used);
        $template->setContent('<p>Convocation</p>');
        $em->persist($template);
        $em->flush();
        $usedId = $used->getId();
        $unusedId = $em->getRepository(DocumentType::class)->findOneBy(['code' => 'PAYMENT_CERTIFICATE'])->getId();

        $token = $this->token(UserFixtures::ADMIN_EMAIL);

        [$status, $data] = $this->api('DELETE', '/api/document-types/' . $usedId, $token);
        $this->assertSame(Response::HTTP_CONFLICT, $status);
        $this->assertStringContainsString('Impossible de supprimer le type', json_encode($data, JSON_UNESCAPED_UNICODE));

        // Contrôle : un type inutilisé se supprime
        [$status] = $this->api('DELETE', '/api/document-types/' . $unusedId, $token);
        $this->assertSame(Response::HTTP_NO_CONTENT, $status);

        $this->em()->clear();
        $this->assertNotNull($this->em()->getRepository(DocumentType::class)->find($usedId));
        $this->assertNull($this->em()->getRepository(DocumentType::class)->find($unusedId));
    }

    public function testDeleteUsedSkillIsConflict(): void
    {
        static::createClient();
        $this->loadFixtures();

        // Reading est utilisée par des épreuves
        $skillId = $this->skill('Reading')->getId();

        [$status, $data] = $this->api('DELETE', '/api/skills/' . $skillId, $this->token(UserFixtures::ADMIN_EMAIL));
        $this->assertSame(Response::HTTP_CONFLICT, $status);
        $this->assertStringContainsString('encore utilisée', json_encode($data, JSON_UNESCAPED_UNICODE));

        $this->em()->clear();
        $this->assertNotNull($this->em()->getRepository(Skill::class)->find($skillId));
    }

    public function testDeleteSkillWithChildrenIsConflict(): void
    {
        static::createClient();
        $this->loadFixtures();

        $em = $this->em();
        $parent = (new Skill())->setLabel('Oral');
        $child = (new Skill())->setLabel('Phonétique')->setParent($parent);
        $em->persist($parent);
        $em->persist($child);
        $em->flush();
        $parentId = $parent->getId();
        $childId = $child->getId();

        $token = $this->token(UserFixtures::ADMIN_EMAIL);

        [$status, $data] = $this->api('DELETE', '/api/skills/' . $parentId, $token);
        $this->assertSame(Response::HTTP_CONFLICT, $status);
        $this->assertStringContainsString('sous-compétences', json_encode($data, JSON_UNESCAPED_UNICODE));

        // La sous-compétence, inutilisée et sans enfant, se supprime ; le parent ensuite aussi
        [$status] = $this->api('DELETE', '/api/skills/' . $childId, $token);
        $this->assertSame(Response::HTTP_NO_CONTENT, $status);
        [$status] = $this->api('DELETE', '/api/skills/' . $parentId, $token);
        $this->assertSame(Response::HTTP_NO_CONTENT, $status);
    }
}
