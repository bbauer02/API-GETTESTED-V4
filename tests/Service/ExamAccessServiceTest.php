<?php

namespace App\Tests\Service;

use App\Entity\CandidateResponse;
use App\Entity\EnrollmentExam;
use App\Entity\EnrollmentSession;
use App\Entity\Exam;
use App\Entity\Invoice;
use App\Entity\MatchingPair;
use App\Entity\MatchingQuestion;
use App\Entity\ScheduledExam;
use App\Entity\Session;
use App\Entity\Subject;
use App\Entity\User;
use App\Enum\EnrollmentExamStatusEnum;
use App\Enum\InvoiceStatusEnum;
use App\Enum\InvoiceTypeEnum;
use App\Enum\SessionStatusEnum;
use App\Enum\SubjectStatusEnum;
use App\Exception\ConflictHttpException;
use App\Service\DocumentAccessService;
use App\Service\ExamAccessService;
use App\Service\ResponseGraderService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Règles d'accès à une épreuve en ligne et correction des questions d'appariement.
 */
class ExamAccessServiceTest extends TestCase
{
    private ExamAccessService $service;

    protected function setUp(): void
    {
        $this->service = new ExamAccessService(new DocumentAccessService());
    }

    private static function withId(object $entity): object
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, Uuid::v7());

        return $entity;
    }

    /**
     * Épreuve en ligne de 60 minutes qui commence à $startDate, session validée, inscription réglée.
     *
     * @return array{0: EnrollmentExam, 1: User}
     */
    private function onlineExam(\DateTimeInterface $startDate, int $duration = 60): array
    {
        $candidate = self::withId(new User());

        $session = new Session();
        $session->setStatus(SessionStatusEnum::VALIDATED);

        $exam = new Exam();
        $exam->setDuration($duration);

        $subject = new Subject();
        $subject->setStatus(SubjectStatusEnum::LOCKED);

        $scheduledExam = new ScheduledExam();
        $scheduledExam->setSession($session);
        $scheduledExam->setExam($exam);
        $scheduledExam->setStartDate($startDate);
        $scheduledExam->setSubject($subject);

        $enrollment = new EnrollmentSession();
        $enrollment->setUser($candidate);
        $enrollment->setSession($session);

        $enrollmentExam = self::withId(new EnrollmentExam());
        $enrollmentExam->setEnrollmentSession($enrollment);
        $enrollmentExam->setScheduledExam($scheduledExam);
        $enrollmentExam->setStatus(EnrollmentExamStatusEnum::REGISTERED);

        return [$enrollmentExam, $candidate];
    }

    public function testCandidateCanStartDuringTheSlot(): void
    {
        [$enrollmentExam, $candidate] = $this->onlineExam(new \DateTime('-10 minutes'));

        $this->service->assertCanStart($enrollmentExam, $candidate);
        $this->addToAssertionCount(1);
    }

    public function testCannotStartBeforeTheExamOpens(): void
    {
        [$enrollmentExam, $candidate] = $this->onlineExam(new \DateTime('+1 hour'));

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('L\'épreuve ouvrira le');
        $this->service->assertCanStart($enrollmentExam, $candidate);
    }

    public function testCannotStartAfterTheSlot(): void
    {
        [$enrollmentExam, $candidate] = $this->onlineExam(new \DateTime('-2 hours'));

        $this->expectException(ConflictHttpException::class);
        $this->service->assertCanStart($enrollmentExam, $candidate);
    }

    public function testAnotherUserCannotStart(): void
    {
        [$enrollmentExam] = $this->onlineExam(new \DateTime('-10 minutes'));

        $this->expectException(AccessDeniedHttpException::class);
        $this->service->assertCanStart($enrollmentExam, self::withId(new User()));
    }

    public function testUnpaidEnrollmentCannotStart(): void
    {
        [$enrollmentExam, $candidate] = $this->onlineExam(new \DateTime('-10 minutes'));

        $invoice = new Invoice();
        $invoice->setInvoiceType(InvoiceTypeEnum::INVOICE);
        $invoice->setStatus(InvoiceStatusEnum::ISSUED);
        $invoice->setTotalTTC(42);
        $enrollmentExam->getEnrollmentSession()->getInvoices()->add($invoice);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Réglez votre inscription');
        $this->service->assertCanStart($enrollmentExam, $candidate);
    }

    public function testDeadlineCountsFromFirstStartAndRefusesLateAnswers(): void
    {
        [$enrollmentExam, $candidate] = $this->onlineExam(new \DateTime('-50 minutes'), 30);
        $enrollmentExam->setStartedAt(new \DateTime('-31 minutes'));

        $this->assertEquals(
            (new \DateTime('-1 minute'))->format('Y-m-d H:i'),
            $this->service->deadline($enrollmentExam)->format('Y-m-d H:i'),
        );

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('temps de l\'épreuve est écoulé');
        $this->service->assertCanAnswer($enrollmentExam, $candidate);
    }

    public function testMatchingIsGradedWithOpaqueTokensOnly(): void
    {
        $grader = new ResponseGraderService('test-secret');
        [$enrollmentExam] = $this->onlineExam(new \DateTime('-10 minutes'));
        $scope = $enrollmentExam->getId()->toRfc4122();

        $question = new MatchingQuestion();
        $pairs = [];
        foreach (['chat' => 'cat', 'chien' => 'dog'] as $left => $right) {
            $pair = self::withId(new MatchingPair());
            $pair->setLeftText($left);
            $pair->setRightText($right);
            $question->addMatchingPair($pair);
            $pairs[] = $pair->getId()->toRfc4122();
        }

        $response = new CandidateResponse();
        $response->setEnrollmentExam($enrollmentExam);

        // Bonne réponse : jetons de droite correspondant à chaque paire
        $response->setGivenAnswer(['pairs' => array_combine($pairs, array_map(fn ($id) => $grader->matchingToken($id, $scope), $pairs))]);
        $grader->grade($response, $question);
        $this->assertEqualsWithDelta($question->getMaxPoints(), $response->getScore(), 0.001);

        // « Chaque élément associé à lui-même » ne rapporte plus rien
        $response->setGivenAnswer(['pairs' => array_combine($pairs, $pairs)]);
        $grader->grade($response, $question);
        $this->assertEqualsWithDelta(0.0, $response->getScore(), 0.001);
    }
}
