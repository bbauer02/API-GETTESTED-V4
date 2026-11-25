<?php

namespace App\Tests\Api;

use App\DataFixtures\AssessmentFixtures;
use App\DataFixtures\InstituteFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\ScheduledExam;
use App\Entity\Session;
use App\Entity\Subject;
use App\Entity\SubjectQuestion;
use App\Enum\SessionStatusEnum;
use App\Enum\SubjectStatusEnum;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sujets (réordonnancement réel, statut non modifiable par PATCH) et cohérence des dates de session
 * à la création comme à la modification.
 */
class SubjectAndSessionRulesTest extends WebTestCase
{
    use ApiTestTrait;
    use AuditTestTrait;

    /**
     * Sujet de l'épreuve « Expression écrite » du test personnalisé, avec 3 QCM aux positions 1, 2, 3.
     *
     * @return array{0: string, 1: list<string>} id du sujet, ids des SubjectQuestion dans l'ordre initial
     */
    private function createSubjectWithQuestions(SubjectStatusEnum $status = SubjectStatusEnum::DRAFT): array
    {
        $em = $this->em();
        $custom = $this->assessment(AssessmentFixtures::CUSTOM_LABEL);
        $session = $em->getRepository(Session::class)->findOneBy(['assessment' => $custom]);
        $scheduledExam = $em->getRepository(ScheduledExam::class)->findOneBy(['session' => $session], ['startDate' => 'ASC']);

        $subject = (new Subject())
            ->setTitre('Sujet à réordonner')
            ->setScheduledExam($scheduledExam)
            ->setStatus($status);
        $em->persist($subject);

        $ids = [];
        foreach ([1, 2, 3] as $position) {
            $question = $this->createMcq($custom, $this->level('A1'), 'Question ' . $position);
            $subjectQuestion = (new SubjectQuestion())
                ->setSubject($subject)
                ->setQuestion($question)
                ->setPosition($position);
            $subject->addSubjectQuestion($subjectQuestion);
            $em->persist($subjectQuestion);
            $em->flush();
            $ids[] = (string) $subjectQuestion->getId();
        }

        return [(string) $subject->getId(), $ids];
    }

    /** @return array<string, int> position de chaque SubjectQuestion, lue en base */
    private function positions(array $ids): array
    {
        $this->em()->clear();
        $positions = [];
        foreach ($ids as $id) {
            $positions[$id] = $this->em()->getRepository(SubjectQuestion::class)->find($id)->getPosition();
        }

        return $positions;
    }

    // ========================
    // 14. Sujets
    // ========================

    public function testReorderSubjectQuestions(): void
    {
        static::createClient();
        $this->loadFixtures();
        [$subjectId, [$sq1, $sq2, $sq3]] = $this->createSubjectWithQuestions();
        $token = $this->token(UserFixtures::USER1_EMAIL);

        [$status] = $this->api('POST', '/api/subjects/' . $subjectId . '/questions/reorder', $token, [
            'order' => [$sq3, $sq1, $sq2],
        ]);
        $this->assertSame(Response::HTTP_NO_CONTENT, $status);
        $this->assertSame([$sq1 => 2, $sq2 => 3, $sq3 => 1], $this->positions([$sq1, $sq2, $sq3]));

        // L'ordre est visible dans la liste des questions du sujet
        [$status, $list] = $this->api('GET', '/api/subjects/' . $subjectId . '/questions', $token);
        $this->assertSame(Response::HTTP_OK, $status);
        usort($list, fn ($a, $b) => $a['position'] <=> $b['position']);
        $this->assertSame([$sq3, $sq1, $sq2], array_column($list, 'id'));

        // Ordre incomplet : refusé, positions inchangées
        [$status] = $this->api('POST', '/api/subjects/' . $subjectId . '/questions/reorder', $token, [
            'order' => [$sq1, $sq2],
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $status);
        $this->assertSame([$sq1 => 2, $sq2 => 3, $sq3 => 1], $this->positions([$sq1, $sq2, $sq3]));
    }

    public function testReorderRefusedOnLockedSubject(): void
    {
        static::createClient();
        $this->loadFixtures();
        [$subjectId, [$sq1, $sq2, $sq3]] = $this->createSubjectWithQuestions(SubjectStatusEnum::LOCKED);

        [$status] = $this->api('POST', '/api/subjects/' . $subjectId . '/questions/reorder', $this->token(UserFixtures::USER1_EMAIL), [
            'order' => [$sq3, $sq2, $sq1],
        ]);
        $this->assertSame(Response::HTTP_FORBIDDEN, $status);
        $this->assertSame([$sq1 => 1, $sq2 => 2, $sq3 => 3], $this->positions([$sq1, $sq2, $sq3]));
    }

    public function testPatchSubjectCannotChangeStatus(): void
    {
        static::createClient();
        $this->loadFixtures();
        [$subjectId] = $this->createSubjectWithQuestions();

        [$status, $data] = $this->api('PATCH', '/api/subjects/' . $subjectId, $this->token(UserFixtures::USER1_EMAIL), [
            'titre' => 'Sujet renommé',
            'status' => 'LOCKED',
        ]);
        $this->assertSame(Response::HTTP_OK, $status);
        $this->assertSame('Sujet renommé', $data['titre']);
        $this->assertSame('DRAFT', $data['status']);

        $this->em()->clear();
        $subject = $this->em()->getRepository(Subject::class)->find($subjectId);
        $this->assertSame(SubjectStatusEnum::DRAFT, $subject->getStatus());
        $this->assertNull($subject->getLockedAt());
    }

    // ========================
    // 15. Dates de session
    // ========================

    public function testCreateSessionWithIncoherentDatesIs422(): void
    {
        static::createClient();
        $this->loadFixtures();

        $instituteId = $this->institute(InstituteFixtures::INSTITUTE1_LABEL)->getId();
        $assessmentIri = '/api/assessments/' . $this->assessment(AssessmentFixtures::CUSTOM_LABEL)->getId();
        $token = $this->token(UserFixtures::USER1_EMAIL);

        $cases = [
            'La date de fin doit être postérieure à la date de début.' => [
                'start' => '2027-05-01T09:00:00+00:00',
                'end' => '2027-05-01T09:00:00+00:00',
                'limitDateSubscribe' => '2027-04-25T23:59:59+00:00',
            ],
            "La date limite d'inscription doit précéder le début de la session." => [
                'start' => '2027-05-01T09:00:00+00:00',
                'end' => '2027-05-01T17:00:00+00:00',
                'limitDateSubscribe' => '2027-05-01T09:00:00+00:00',
            ],
        ];

        foreach ($cases as $message => $dates) {
            [$status, $data] = $this->api('POST', '/api/institutes/' . $instituteId . '/sessions', $token, $dates + [
                'placesAvailable' => 20,
                'assessment' => $assessmentIri,
            ]);
            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $status, $message);
            $this->assertSame($message, $data['detail'] ?? null);
        }

        // Fin avant le début : même refus
        [$status] = $this->api('POST', '/api/institutes/' . $instituteId . '/sessions', $token, [
            'start' => '2027-05-01T09:00:00+00:00',
            'end' => '2027-04-30T17:00:00+00:00',
            'assessment' => $assessmentIri,
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $status);
    }

    public function testPatchSessionWithIncoherentDatesIs422(): void
    {
        static::createClient();
        $this->loadFixtures();

        $session = $this->em()->getRepository(Session::class)->findOneBy([
            'institute' => $this->institute(InstituteFixtures::INSTITUTE1_LABEL),
            'status' => SessionStatusEnum::DRAFT,
        ]);
        $sessionId = $session->getId();
        $start = \DateTimeImmutable::createFromInterface($session->getStart());
        $originalEnd = $session->getEnd()->format(\DateTimeInterface::ATOM);
        $token = $this->token(UserFixtures::USER1_EMAIL);

        [$status, $data] = $this->api('PATCH', '/api/sessions/' . $sessionId, $token, [
            'end' => $start->modify('-1 hour')->format(\DateTimeInterface::ATOM),
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $status);
        $this->assertSame('La date de fin doit être postérieure à la date de début.', $data['detail'] ?? null);

        [$status, $data] = $this->api('PATCH', '/api/sessions/' . $sessionId, $token, [
            'limitDateSubscribe' => $start->modify('+1 hour')->format(\DateTimeInterface::ATOM),
        ]);
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $status);
        $this->assertSame("La date limite d'inscription doit précéder le début de la session.", $data['detail'] ?? null);

        $this->em()->clear();
        $this->assertSame($originalEnd, $this->em()->getRepository(Session::class)->find($sessionId)->getEnd()->format(\DateTimeInterface::ATOM));

        // Contrôle : des dates cohérentes sont acceptées
        [$status] = $this->api('PATCH', '/api/sessions/' . $sessionId, $token, [
            'limitDateSubscribe' => $start->modify('-2 days')->format(\DateTimeInterface::ATOM),
        ]);
        $this->assertSame(Response::HTTP_OK, $status);
    }
}
