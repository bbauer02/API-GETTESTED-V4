<?php

namespace App\Tests\Api;

use App\DataFixtures\AssessmentFixtures;
use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Assessment;
use App\Entity\Exam;
use App\Entity\Question;
use App\Enum\InstituteRoleEnum;
use App\Enum\QuestionStatusEnum;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Banque de questions : droits de création (securityPostDenormalize), interdiction de déplacer
 * une épreuve / une question / un test hors de l'institut, workflow de statut et question calibrée.
 */
class QuestionWorkflowTest extends WebTestCase
{
    use ApiTestTrait;
    use AuditTestTrait;

    private const IF = InstituteFixtures::INSTITUTE1_LABEL;
    private const TENRI = InstituteFixtures::INSTITUTE2_LABEL;

    /** Corps minimal valide d'un QCU (format du formulaire front). */
    private function mcqPayload(Assessment $assessment, string $levelRef, string $label = 'Question de test'): array
    {
        return [
            'assessment' => '/api/assessments/' . $assessment->getId(),
            'level' => '/api/levels/' . $this->level($levelRef)->getId(),
            'skills' => [],
            'label' => $label,
            'text' => 'Quelle est la capitale de la France ?',
            'instruction' => null,
            'duration' => null,
            'maxPoints' => 1,
            'isMultipleAnswer' => false,
            'shuffleChoices' => true,
            'choices' => [
                ['text' => 'Paris', 'weight' => 1, 'position' => 0, 'feedback' => null],
                ['text' => 'Lyon', 'weight' => 0, 'position' => 1, 'feedback' => null],
            ],
        ];
    }

    private function customAssessment(): Assessment
    {
        return $this->assessment(AssessmentFixtures::CUSTOM_LABEL);
    }

    // ========================
    // 4. Création de question
    // ========================

    public function testCandidateCannotCreateQuestion(): void
    {
        static::createClient();
        $this->loadFixtures();

        [$status] = $this->api('POST', '/api/mcq-questions', $this->token(UserFixtures::USER2_EMAIL), $this->mcqPayload($this->customAssessment(), 'A1'));
        $this->assertSame(Response::HTTP_FORBIDDEN, $status);
    }

    public function testAdminOfAnotherInstituteCannotCreateQuestion(): void
    {
        static::createClient();
        $this->loadFixtures();

        // Christophe devient ADMIN de Tenri, simple acheteur (BUYER) du test personnalisé
        $this->setMembership(UserFixtures::USER2_EMAIL, self::TENRI, InstituteRoleEnum::ADMIN);
        $payload = $this->mcqPayload($this->customAssessment(), 'A1');

        [$status] = $this->api('POST', '/api/mcq-questions', $this->token(UserFixtures::USER2_EMAIL), $payload);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status);
        $this->assertSame(0, $this->em()->getRepository(Question::class)->count([]));
    }

    public function testOwnerInstituteAdminAndTeacherCanCreateQuestion(): void
    {
        static::createClient();
        $this->loadFixtures();
        $custom = $this->customAssessment();
        $customId = $custom->getId();

        // ADMIN de l'institut propriétaire
        [$status, $data] = $this->api('POST', '/api/mcq-questions', $this->token(UserFixtures::USER1_EMAIL), $this->mcqPayload($custom, 'A1', 'Question admin'));
        $this->assertSame(Response::HTTP_CREATED, $status, json_encode($data, JSON_UNESCAPED_UNICODE));
        $this->assertSame('DRAFT', $data['status']);
        $this->assertSame('mcq', $data['type']);
        $this->assertCount(2, $data['choices']);

        // TEACHER de l'institut propriétaire
        $this->setMembership(UserFixtures::USER2_EMAIL, self::IF, InstituteRoleEnum::TEACHER);
        [$status, $data] = $this->api('POST', '/api/mcq-questions', $this->token(UserFixtures::USER2_EMAIL), $this->mcqPayload($this->customAssessment(), 'A2', 'Question enseignant'));
        $this->assertSame(Response::HTTP_CREATED, $status, json_encode($data, JSON_UNESCAPED_UNICODE));

        $this->assertSame(2, $this->em()->getRepository(Question::class)->count(['assessment' => $customId]));
    }

    public function testLevelOutsideAssessmentIs422(): void
    {
        static::createClient();
        $this->loadFixtures();

        // B1 n'est pas un niveau du test personnalisé (A1, A2)
        [$status, $data] = $this->api('POST', '/api/mcq-questions', $this->token(UserFixtures::USER1_EMAIL), $this->mcqPayload($this->customAssessment(), 'B1'));
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $status);
        $this->assertStringContainsString("Le niveau choisi n'appartient pas au test de la question.", json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    // ========================
    // 5. PATCH vers un autre institut
    // ========================

    public function testExamCannotBeMovedToAnotherInstituteAssessment(): void
    {
        static::createClient();
        $this->loadFixtures();

        $foreign = $this->createAssessmentOwnedBy(self::TENRI, 'Test de Tenri');
        $foreignId = $foreign->getId();
        $exam = $this->em()->getRepository(Exam::class)->findOneBy(['label' => 'Expression écrite - Niveau A1/A2']);
        $examId = $exam->getId();
        $customId = $this->customAssessment()->getId();
        $token = $this->token(UserFixtures::USER1_EMAIL);

        [$status] = $this->api('PATCH', '/api/exams/' . $examId, $token, ['assessment' => '/api/assessments/' . $foreignId]);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status);

        $this->em()->clear();
        $this->assertTrue($this->em()->getRepository(Exam::class)->find($examId)->getAssessment()->getId()->equals($customId));

        // Contrôle : une modification qui reste dans le test est acceptée
        [$status, $data] = $this->api('PATCH', '/api/exams/' . $examId, $token, ['duration' => 75]);
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertSame(75, $data['duration']);
    }

    public function testQuestionCannotBeMovedToAnotherInstituteAssessment(): void
    {
        static::createClient();
        $this->loadFixtures();

        $foreignId = $this->createAssessmentOwnedBy(self::TENRI, 'Test de Tenri', 'A1')->getId();
        $customId = $this->customAssessment()->getId();
        $question = $this->createMcq($this->customAssessment(), $this->level('A1'), 'Question à déplacer');
        $questionId = $question->getId();

        [$status] = $this->api('PATCH', '/api/questions/' . $questionId, $this->token(UserFixtures::USER1_EMAIL), [
            'assessment' => '/api/assessments/' . $foreignId,
        ]);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status);

        $this->em()->clear();
        $this->assertTrue($this->em()->getRepository(Question::class)->find($questionId)->getAssessment()->getId()->equals($customId));
    }

    public function testInstituteAdminCannotMakeAssessmentInternal(): void
    {
        static::createClient();
        $this->loadFixtures();

        $customId = $this->customAssessment()->getId();

        [$status] = $this->api('PATCH', '/api/assessments/' . $customId, $this->token(UserFixtures::USER1_EMAIL), ['isInternal' => true]);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status);

        $this->em()->clear();
        $this->assertFalse($this->em()->getRepository(Assessment::class)->find($customId)->isInternal());
    }

    // ========================
    // 11. Workflow de statut et question calibrée
    // ========================

    public function testPretestOnlyFromDraft(): void
    {
        static::createClient();
        $this->loadFixtures();

        $questionId = $this->createMcq($this->customAssessment(), $this->level('A1'), 'Question brouillon')->getId();
        $token = $this->token(UserFixtures::USER1_EMAIL);

        [$status, $data] = $this->api('POST', '/api/questions/' . $questionId . '/pretest', $token);
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertSame('PRETESTING', $data['status']);

        // Déjà en pré-test : conflit
        [$status, $data] = $this->api('POST', '/api/questions/' . $questionId . '/pretest', $token);
        $this->assertSame(Response::HTTP_CONFLICT, $status);
        $this->assertStringContainsString('brouillon', json_encode($data, JSON_UNESCAPED_UNICODE));

        $this->em()->clear();
        $this->assertSame(QuestionStatusEnum::PRETESTING, $this->em()->getRepository(Question::class)->find($questionId)->getStatus());
    }

    public function testRetireSetsRetiredAtAndReason(): void
    {
        static::createClient();
        $this->loadFixtures();

        $questionId = $this->createMcq($this->customAssessment(), $this->level('A1'), 'Question calibrée', QuestionStatusEnum::CALIBRATED)->getId();

        [$status, $data] = $this->api('POST', '/api/questions/' . $questionId . '/retire', $this->token(UserFixtures::USER1_EMAIL), [
            'reason' => 'Énoncé ambigu',
        ]);
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertSame('RETIRED', $data['status']);
        $this->assertNotEmpty($data['retiredAt']);
        $this->assertSame('Énoncé ambigu', $data['retiredReason']);

        $this->em()->clear();
        $question = $this->em()->getRepository(Question::class)->find($questionId);
        $this->assertSame(QuestionStatusEnum::RETIRED, $question->getStatus());
        $this->assertNotNull($question->getRetiredAt());
    }

    public function testCalibratedQuestionOnlyLabelAndInstructionEditable(): void
    {
        static::createClient();
        $this->loadFixtures();

        $questionId = $this->createMcq($this->customAssessment(), $this->level('A1'), 'Question calibrée', QuestionStatusEnum::CALIBRATED)->getId();
        $token = $this->token(UserFixtures::USER1_EMAIL);

        [$status, $data] = $this->api('PATCH', '/api/questions/' . $questionId, $token, [
            'label' => 'Libellé corrigé',
            'instruction' => 'Cochez une réponse.',
        ]);
        $this->assertSame(Response::HTTP_OK, $status, json_encode($data, JSON_UNESCAPED_UNICODE));
        $this->assertSame('Libellé corrigé', $data['label']);
        $this->assertSame('Cochez une réponse.', $data['instruction']);

        foreach ([
            'énoncé' => ['text' => 'Nouvel énoncé'],
            'points' => ['maxPoints' => 3],
            'niveau' => ['level' => '/api/levels/' . $this->level('A2')->getId()],
        ] as $what => $body) {
            [$status, $data] = $this->api('PATCH', '/api/questions/' . $questionId, $token, $body);
            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $status, $what);
            $this->assertStringContainsString('calibrée', json_encode($data, JSON_UNESCAPED_UNICODE), $what);
        }

        $this->em()->clear();
        $question = $this->em()->getRepository(Question::class)->find($questionId);
        $this->assertSame('Énoncé de Question calibrée', $question->getText());
        $this->assertSame(1.0, $question->getMaxPoints());
        $this->assertSame('A1', $question->getLevel()->getRef());
        $this->assertSame('Libellé corrigé', $question->getLabel());
    }
}
