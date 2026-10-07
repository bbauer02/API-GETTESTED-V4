<?php

namespace App\Entity;

use App\Enum\CandidateResponseStatusEnum;
use App\Repository\CandidateResponseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CandidateResponseRepository::class)]
#[ORM\Table(name: 'candidate_response')]
#[ORM\Index(columns: ['answered_at'], name: 'idx_response_answered_at')]
class CandidateResponse
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['response:read', 'exam_result:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['response:read', 'response:write', 'exam_result:read'])]
    private array $givenAnswer = [];

    #[ORM\Column(enumType: CandidateResponseStatusEnum::class)]
    #[Groups(['response:read', 'response:write', 'exam_result:read'])]
    private CandidateResponseStatusEnum $status;

    #[ORM\Column(type: 'float')]
    #[Groups(['response:read', 'response:write', 'exam_result:read'])]
    private float $score = 0.0;

    #[ORM\Column(type: 'integer')]
    #[Groups(['response:read', 'response:write', 'exam_result:read'])]
    private int $responseTimeMs = 0;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['response:read', 'response:write', 'exam_result:read'])]
    private \DateTime $answeredAt;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['response:read', 'exam_result:read'])]
    private ?float $thetaAtAnswer = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['response:read', 'exam_result:read'])]
    private ?float $informationValue = null;

    #[ORM\ManyToOne(targetEntity: Question::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['response:read', 'exam_result:read'])]
    private ?Question $question = null;

    #[ORM\ManyToOne(targetEntity: SubjectQuestion::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['response:read', 'exam_result:read'])]
    private ?SubjectQuestion $subjectQuestion = null;

    #[ORM\ManyToOne(targetEntity: EnrollmentExam::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['response:read', 'exam_result:read'])]
    private ?EnrollmentExam $enrollmentExam = null;

    #[ORM\ManyToOne(targetEntity: PracticeSession::class, inversedBy: 'responses')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['response:read'])]
    private ?PracticeSession $practiceSession = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getGivenAnswer(): array
    {
        return $this->givenAnswer;
    }

    public function setGivenAnswer(array $givenAnswer): static
    {
        $this->givenAnswer = $givenAnswer;
        return $this;
    }

    public function getStatus(): CandidateResponseStatusEnum
    {
        return $this->status;
    }

    public function setStatus(CandidateResponseStatusEnum $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): static
    {
        $this->score = $score;
        return $this;
    }

    public function getResponseTimeMs(): int
    {
        return $this->responseTimeMs;
    }

    public function setResponseTimeMs(int $responseTimeMs): static
    {
        $this->responseTimeMs = $responseTimeMs;
        return $this;
    }

    public function getAnsweredAt(): \DateTime
    {
        return $this->answeredAt;
    }

    public function setAnsweredAt(\DateTime $answeredAt): static
    {
        $this->answeredAt = $answeredAt;
        return $this;
    }

    public function getThetaAtAnswer(): ?float
    {
        return $this->thetaAtAnswer;
    }

    public function setThetaAtAnswer(?float $thetaAtAnswer): static
    {
        $this->thetaAtAnswer = $thetaAtAnswer;
        return $this;
    }

    public function getInformationValue(): ?float
    {
        return $this->informationValue;
    }

    public function setInformationValue(?float $informationValue): static
    {
        $this->informationValue = $informationValue;
        return $this;
    }

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): static
    {
        $this->question = $question;
        return $this;
    }

    public function getSubjectQuestion(): ?SubjectQuestion
    {
        return $this->subjectQuestion;
    }

    public function setSubjectQuestion(?SubjectQuestion $subjectQuestion): static
    {
        $this->subjectQuestion = $subjectQuestion;
        return $this;
    }

    public function getEnrollmentExam(): ?EnrollmentExam
    {
        return $this->enrollmentExam;
    }

    public function setEnrollmentExam(?EnrollmentExam $enrollmentExam): static
    {
        $this->enrollmentExam = $enrollmentExam;
        return $this;
    }

    public function getPracticeSession(): ?PracticeSession
    {
        return $this->practiceSession;
    }

    public function setPracticeSession(?PracticeSession $practiceSession): static
    {
        $this->practiceSession = $practiceSession;
        return $this;
    }
}
