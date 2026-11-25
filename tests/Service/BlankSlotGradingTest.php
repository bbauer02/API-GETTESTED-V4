<?php

namespace App\Tests\Service;

use App\Entity\BlankSlot;
use App\Entity\CandidateResponse;
use App\Entity\FillBlankQuestion;
use App\Enum\CandidateResponseStatusEnum;
use App\Service\ResponseGraderService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Texte à trous : chaque réponse est associée à son trou par une clé stable (blankSlotKeys),
 * que les positions soient distinctes ou toutes à 0 (anciennes questions).
 */
class BlankSlotGradingTest extends TestCase
{
    private ResponseGraderService $grader;

    protected function setUp(): void
    {
        $this->grader = new ResponseGraderService('secret-de-test');
    }

    /**
     * Question dont les trous sont ajoutés dans l'ordre donné ([position, réponse acceptée]).
     * Les identifiants sont posés comme le ferait Doctrine.
     */
    private function question(array $slots): FillBlankQuestion
    {
        $question = (new FillBlankQuestion())->setLabel('Trous')->setText('___ ___ ___')->setMaxPoints(3);
        $idProperty = new \ReflectionProperty(BlankSlot::class, 'id');

        foreach ($slots as [$position, $answer]) {
            $slot = (new BlankSlot())->setPosition($position)->setAcceptedAnswers([$answer]);
            $idProperty->setValue($slot, Uuid::v4());
            $question->addBlankSlot($slot);
        }

        return $question;
    }

    private function grade(FillBlankQuestion $question, array $slots): CandidateResponse
    {
        $response = (new CandidateResponse())->setGivenAnswer(['slots' => $slots]);

        return $this->grader->grade($response, $question);
    }

    /** @return array<string, string> clé => première réponse acceptée du trou */
    private function keyMap(FillBlankQuestion $question): array
    {
        $map = [];
        foreach ($this->grader->blankSlotKeys($question) as ['slot' => $slot, 'key' => $key]) {
            $map[$key] = $slot->getAcceptedAnswers()[0];
        }

        return $map;
    }

    public function testDistinctPositionsAreKeysInTextOrder(): void
    {
        // Trous enregistrés dans le désordre : l'ordre de l'énoncé est celui des positions
        $question = $this->question([[2, 'chat'], [0, 'le'], [1, 'noir']]);

        $this->assertSame(['0' => 'le', '1' => 'noir', '2' => 'chat'], $this->keyMap($question));

        $response = $this->grade($question, ['0' => 'le', '1' => 'noir', '2' => 'chat']);
        $this->assertSame(CandidateResponseStatusEnum::CORRECT, $response->getStatus());
        $this->assertSame(3.0, $response->getScore());

        // Réponses permutées : aucune n'est sur son trou
        $response = $this->grade($question, ['0' => 'chat', '1' => 'le', '2' => 'noir']);
        $this->assertSame(CandidateResponseStatusEnum::INCORRECT, $response->getStatus());
        $this->assertSame(0.0, $response->getScore());
    }

    public function testNonContiguousPositionsKeepTheirValue(): void
    {
        $question = $this->question([[5, 'deux'], [1, 'un']]);

        $this->assertSame(['1' => 'un', '5' => 'deux'], $this->keyMap($question));

        $response = $this->grade($question, ['1' => 'un', '5' => 'deux']);
        $this->assertSame(CandidateResponseStatusEnum::CORRECT, $response->getStatus());

        // Réponses indexées par rang (0, 1) au lieu des positions : aucune ne tombe sur son trou
        $response = $this->grade($question, ['0' => 'un', '1' => 'deux']);
        $this->assertSame(CandidateResponseStatusEnum::INCORRECT, $response->getStatus());
    }

    public function testAllPositionsZeroUseRankInRecordOrder(): void
    {
        // Anciennes questions : toutes les positions à 0, la clé est le rang d'enregistrement
        $question = $this->question([[0, 'un'], [0, 'deux'], [0, 'trois']]);

        $this->assertSame(['0' => 'un', '1' => 'deux', '2' => 'trois'], $this->keyMap($question));

        $response = $this->grade($question, ['0' => 'un', '1' => 'deux', '2' => 'trois']);
        $this->assertSame(CandidateResponseStatusEnum::CORRECT, $response->getStatus());
        $this->assertSame(3.0, $response->getScore());

        // Une seule bonne réponse sur trois : correction partielle, 1 point
        $response = $this->grade($question, ['0' => 'un', '1' => 'trois', '2' => 'deux']);
        $this->assertSame(CandidateResponseStatusEnum::PARTIAL, $response->getStatus());
        $this->assertSame(1.0, $response->getScore());
    }

    public function testSlotIdIsAcceptedAsKey(): void
    {
        $question = $this->question([[0, 'un'], [0, 'deux']]);
        $byId = [];
        foreach ($question->getBlankSlots() as $slot) {
            $byId[$slot->getId()->toRfc4122()] = $slot->getAcceptedAnswers()[0];
        }

        $response = $this->grade($question, $byId);
        $this->assertSame(CandidateResponseStatusEnum::CORRECT, $response->getStatus());
    }
}
