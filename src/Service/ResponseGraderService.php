<?php

namespace App\Service;

use App\Entity\BlankSlot;
use App\Entity\CandidateResponse;
use App\Entity\FillBlankQuestion;
use App\Entity\HighlightQuestion;
use App\Entity\MatchingQuestion;
use App\Entity\MCQQuestion;
use App\Entity\OrderingQuestion;
use App\Entity\Question;
use App\Entity\SubjectQuestion;
use App\Enum\CandidateResponseStatusEnum;

class ResponseGraderService
{
    public function __construct(
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    /**
     * Identifiant opaque d'un élément de droite d'une question d'appariement.
     * Il dépend du secret de l'application et de l'épreuve (ou de l'entraînement) :
     * le candidat ne peut pas en déduire à quel élément de gauche il correspond.
     */
    public function matchingToken(string $pairId, string $scopeId): string
    {
        return substr(hash_hmac('sha256', $pairId . '|' . $scopeId, $this->secret), 0, 24);
    }

    /**
     * Grade a candidate's response and update the CandidateResponse entity.
     *
     * @param CandidateResponse    $response        The response to grade
     * @param Question             $question        The question being answered
     * @param SubjectQuestion|null $subjectQuestion Optional — for pointsOverride
     *
     * @return CandidateResponse The graded response
     */
    public function grade(CandidateResponse $response, Question $question, ?SubjectQuestion $subjectQuestion = null): CandidateResponse
    {
        $givenAnswer = $response->getGivenAnswer();
        $maxPoints = $subjectQuestion?->getPointsOverride() ?? $question->getMaxPoints();

        // Calculate normalized score (0.0 to 1.0) based on question type
        $normalizedScore = match (true) {
            $question instanceof MCQQuestion => $this->gradeMcq($question, $givenAnswer),
            $question instanceof FillBlankQuestion => $this->gradeFillBlank($question, $givenAnswer),
            $question instanceof HighlightQuestion => $this->gradeHighlight($question, $givenAnswer),
            $question instanceof OrderingQuestion => $this->gradeOrdering($question, $givenAnswer),
            $question instanceof MatchingQuestion => $this->gradeMatching($question, $givenAnswer, $this->responseScope($response)),
            default => 0.0,
        };

        // Clamp between 0 and 1
        $normalizedScore = max(0.0, min(1.0, $normalizedScore));

        $finalScore = round($normalizedScore * $maxPoints, 2);

        // Determine status
        $status = match (true) {
            $normalizedScore >= 1.0 => CandidateResponseStatusEnum::CORRECT,
            $normalizedScore > 0.0 => CandidateResponseStatusEnum::PARTIAL,
            default => CandidateResponseStatusEnum::INCORRECT,
        };

        $response->setScore($finalScore);
        $response->setStatus($status);

        return $response;
    }

    // -------- MCQ Grading --------
    // givenAnswer format: {"choiceIds": ["uuid1", "uuid2"]}
    //
    // For single answer: score = weight of selected choice / max weight
    // For multiple answer: score = sum of selected weights / sum of all positive weights
    // Negative weights (penalties) are subtracted

    private function gradeMcq(MCQQuestion $question, array $givenAnswer): float
    {
        $selectedIds = $givenAnswer['choiceIds'] ?? [];
        if (empty($selectedIds)) {
            return 0.0;
        }

        $choices = $question->getChoices();
        $maxPositiveWeight = 0.0;
        $earnedWeight = 0.0;

        foreach ($choices as $choice) {
            $weight = $choice->getWeight();
            if ($weight > 0) {
                $maxPositiveWeight += $weight;
            }

            $choiceId = $choice->getId()->toRfc4122();
            if (in_array($choiceId, $selectedIds, true)) {
                $earnedWeight += $weight; // can be negative (penalty)
            }
        }

        if ($maxPositiveWeight <= 0) {
            return 0.0;
        }

        return $earnedWeight / $maxPositiveWeight;
    }

    // -------- FillBlank Grading --------
    // givenAnswer format: {"slots": {"0": "answer1", "1": "answer2"}}
    //
    // For each slot: check if given answer is in acceptedAnswers
    // Respects caseSensitive and accentSensitive flags
    // Score = correct slots / total slots

    private function gradeFillBlank(FillBlankQuestion $question, array $givenAnswer): float
    {
        $givenSlots = $givenAnswer['slots'] ?? [];
        $blankSlots = $question->getBlankSlots();
        $totalSlots = count($blankSlots);

        if ($totalSlots === 0) {
            return 0.0;
        }

        $correctCount = 0;

        foreach ($blankSlots as $slot) {
            $position = (string) $slot->getPosition();
            $given = $givenSlots[$position] ?? '';
            $acceptedAnswers = $slot->getAcceptedAnswers();

            if ($this->isBlankCorrect($given, $acceptedAnswers, $slot->isCaseSensitive(), $slot->isAccentSensitive())) {
                $correctCount++;
            }
        }

        return $correctCount / $totalSlots;
    }

    private function isBlankCorrect(string $given, array $accepted, bool $caseSensitive, bool $accentSensitive): bool
    {
        $given = trim($given);
        if ($given === '') {
            return false;
        }

        foreach ($accepted as $answer) {
            $answer = trim((string) $answer);
            $g = $given;
            $a = $answer;

            if (!$caseSensitive) {
                $g = mb_strtolower($g);
                $a = mb_strtolower($a);
            }

            if (!$accentSensitive) {
                $g = $this->removeAccents($g);
                $a = $this->removeAccents($a);
            }

            if ($g === $a) {
                return true;
            }
        }

        return false;
    }

    private function removeAccents(string $str): string
    {
        $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC;');

        return $transliterator ? $transliterator->transliterate($str) : $str;
    }

    // -------- Highlight Grading --------
    // givenAnswer format: {"zoneIds": ["uuid1", "uuid2"]}
    //
    // Score = (correctly selected zones - incorrectly selected zones) / total correct zones
    // Minimum 0

    private function gradeHighlight(HighlightQuestion $question, array $givenAnswer): float
    {
        $selectedIds = $givenAnswer['zoneIds'] ?? [];
        $zones = $question->getHighlightZones();

        $totalCorrectZones = 0;
        $correctlySelected = 0;
        $incorrectlySelected = 0;

        foreach ($zones as $zone) {
            $zoneId = $zone->getId()->toRfc4122();
            $isSelected = in_array($zoneId, $selectedIds, true);

            if ($zone->isCorrect()) {
                $totalCorrectZones++;
                if ($isSelected) {
                    $correctlySelected++;
                }
            } elseif ($isSelected) {
                $incorrectlySelected++;
            }
        }

        if ($totalCorrectZones === 0) {
            return 0.0;
        }

        return max(0.0, ($correctlySelected - $incorrectlySelected) / $totalCorrectZones);
    }

    // -------- Ordering Grading --------
    // givenAnswer format: {"order": ["uuid3", "uuid1", "uuid2", "uuid4"]}
    //
    // Uses Kendall tau distance (pairwise comparisons)
    // Score = concordant pairs / total pairs

    private function gradeOrdering(OrderingQuestion $question, array $givenAnswer): float
    {
        $givenOrder = $givenAnswer['order'] ?? [];
        $items = $question->getOrderingItems();

        if (count($items) < 2) {
            return 0.0;
        }

        // Build correct order map: itemId => correctPosition
        $correctPositionMap = [];
        foreach ($items as $item) {
            $correctPositionMap[$item->getId()->toRfc4122()] = $item->getCorrectPosition();
        }

        // Build given position map: itemId => given index
        $givenPositionMap = [];
        foreach ($givenOrder as $index => $itemId) {
            $givenPositionMap[$itemId] = $index;
        }

        // Count concordant pairs (Kendall tau)
        $ids = array_keys($correctPositionMap);
        $n = count($ids);
        $totalPairs = $n * ($n - 1) / 2;
        $concordantPairs = 0;

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $idA = $ids[$i];
                $idB = $ids[$j];

                $correctOrder = $correctPositionMap[$idA] <=> $correctPositionMap[$idB];
                $givenOrderCmp = ($givenPositionMap[$idA] ?? 0) <=> ($givenPositionMap[$idB] ?? 0);

                if ($correctOrder === $givenOrderCmp) {
                    $concordantPairs++;
                }
            }
        }

        return $totalPairs > 0 ? $concordantPairs / $totalPairs : 0.0;
    }

    // -------- Matching Grading --------
    // givenAnswer format: {"pairs": {"uuid1": "uuid2", "uuid3": "uuid4"}}
    // where key = leftText item id, value = matched rightText item id
    // OR simpler: {"pairs": {"0": "B", "1": "A", "2": "C"}}
    // where key = pair position, value = matched position from right column
    //
    // Score = correct matches / total pairs

    /**
     * Réponse attendue : {"pairs": {"<id gauche>": "<jeton droite>"}}.
     * L'id de gauche est l'id de la paire ; le jeton de droite vient de matchingToken().
     */
    private function gradeMatching(MatchingQuestion $question, array $givenAnswer, ?string $scopeId): float
    {
        $givenPairs = $givenAnswer['pairs'] ?? [];
        $pairs = $question->getMatchingPairs();
        $totalPairs = count($pairs);

        if ($totalPairs === 0 || $scopeId === null) {
            return 0.0;
        }

        $correctCount = 0;
        foreach ($pairs as $pair) {
            $pairId = $pair->getId()->toRfc4122();
            if (($givenPairs[$pairId] ?? null) === $this->matchingToken($pairId, $scopeId)) {
                $correctCount++;
            }
        }

        return $correctCount / $totalPairs;
    }

    private function responseScope(CandidateResponse $response): ?string
    {
        return $response->getEnrollmentExam()?->getId()?->toRfc4122()
            ?? $response->getPracticeSession()?->getId()?->toRfc4122();
    }
}
