<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\PracticeSessionStatusEnum;
use App\Repository\PracticeSessionRepository;
use App\State\PracticeAnswerProcessor;
use App\State\PracticeFinishProcessor;
use App\State\PracticeNextQuestionProvider;
use App\State\PracticeStartProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            normalizationContext: ['groups' => ['practice:read']],
        ),
        new Get(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            normalizationContext: ['groups' => ['practice:read']],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            processor: PracticeStartProcessor::class,
            denormalizationContext: ['groups' => ['practice:write']],
            normalizationContext: ['groups' => ['practice:read']],
        ),
    ],
    paginationItemsPerPage: 20,
)]
#[ApiResource(
    uriTemplate: '/practice-sessions/{id}/next-question',
    operations: [
        new Get(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: PracticeNextQuestionProvider::class,
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/practice-sessions/{id}/answer',
    operations: [
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            processor: PracticeAnswerProcessor::class,
            deserialize: false,
            read: true,
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/practice-sessions/{id}/finish',
    operations: [
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            processor: PracticeFinishProcessor::class,
            deserialize: false,
            read: true,
        ),
    ],
)]
#[ORM\Entity(repositoryClass: PracticeSessionRepository::class)]
#[ORM\Table(name: 'practice_session')]
class PracticeSession
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['practice:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(enumType: PracticeSessionStatusEnum::class)]
    #[Groups(['practice:read'])]
    private PracticeSessionStatusEnum $status = PracticeSessionStatusEnum::IN_PROGRESS;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['practice:read'])]
    private \DateTime $startedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['practice:read'])]
    private ?\DateTime $finishedAt = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['practice:read'])]
    private int $totalQuestions = 0;

    #[ORM\Column(type: 'integer')]
    #[Groups(['practice:read'])]
    private int $questionsAnswered = 0;

    #[ORM\Column(type: 'float')]
    #[Groups(['practice:read'])]
    private float $initialTheta = 0.0;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['practice:read'])]
    private ?float $finalTheta = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['practice:read'])]
    private ?float $finalStdError = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['practice:read'])]
    private ?string $estimatedLevel = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['practice:read'])]
    private ?array $skillScores = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['practice:read', 'practice:write'])]
    private int $maxQuestions = 40;

    #[ORM\Column(type: 'float')]
    #[Groups(['practice:read', 'practice:write'])]
    private float $stoppingSeThreshold = 0.3;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['practice:read'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Assessment::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['practice:read', 'practice:write'])]
    private ?Assessment $assessment = null;

    #[ORM\ManyToOne(targetEntity: Level::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['practice:read', 'practice:write'])]
    private ?Level $level = null;

    /** @var Collection<int, CandidateResponse> */
    #[ORM\OneToMany(targetEntity: CandidateResponse::class, mappedBy: 'practiceSession', cascade: ['persist'])]
    #[Groups(['practice:read'])]
    private Collection $responses;

    public function __construct()
    {
        $this->startedAt = new \DateTime();
        $this->responses = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getStatus(): PracticeSessionStatusEnum
    {
        return $this->status;
    }

    public function setStatus(PracticeSessionStatusEnum $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStartedAt(): \DateTime
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTime $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getFinishedAt(): ?\DateTime
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTime $finishedAt): static
    {
        $this->finishedAt = $finishedAt;
        return $this;
    }

    public function getTotalQuestions(): int
    {
        return $this->totalQuestions;
    }

    public function setTotalQuestions(int $totalQuestions): static
    {
        $this->totalQuestions = $totalQuestions;
        return $this;
    }

    public function getQuestionsAnswered(): int
    {
        return $this->questionsAnswered;
    }

    public function setQuestionsAnswered(int $questionsAnswered): static
    {
        $this->questionsAnswered = $questionsAnswered;
        return $this;
    }

    public function getInitialTheta(): float
    {
        return $this->initialTheta;
    }

    public function setInitialTheta(float $initialTheta): static
    {
        $this->initialTheta = $initialTheta;
        return $this;
    }

    public function getFinalTheta(): ?float
    {
        return $this->finalTheta;
    }

    public function setFinalTheta(?float $finalTheta): static
    {
        $this->finalTheta = $finalTheta;
        return $this;
    }

    public function getFinalStdError(): ?float
    {
        return $this->finalStdError;
    }

    public function setFinalStdError(?float $finalStdError): static
    {
        $this->finalStdError = $finalStdError;
        return $this;
    }

    public function getEstimatedLevel(): ?string
    {
        return $this->estimatedLevel;
    }

    public function setEstimatedLevel(?string $estimatedLevel): static
    {
        $this->estimatedLevel = $estimatedLevel;
        return $this;
    }

    public function getSkillScores(): ?array
    {
        return $this->skillScores;
    }

    public function setSkillScores(?array $skillScores): static
    {
        $this->skillScores = $skillScores;
        return $this;
    }

    public function getMaxQuestions(): int
    {
        return $this->maxQuestions;
    }

    public function setMaxQuestions(int $maxQuestions): static
    {
        $this->maxQuestions = $maxQuestions;
        return $this;
    }

    public function getStoppingSeThreshold(): float
    {
        return $this->stoppingSeThreshold;
    }

    public function setStoppingSeThreshold(float $stoppingSeThreshold): static
    {
        $this->stoppingSeThreshold = $stoppingSeThreshold;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getAssessment(): ?Assessment
    {
        return $this->assessment;
    }

    public function setAssessment(?Assessment $assessment): static
    {
        $this->assessment = $assessment;
        return $this;
    }

    public function getLevel(): ?Level
    {
        return $this->level;
    }

    public function setLevel(?Level $level): static
    {
        $this->level = $level;
        return $this;
    }

    /** @return Collection<int, CandidateResponse> */
    public function getResponses(): Collection
    {
        return $this->responses;
    }

    public function addResponse(CandidateResponse $response): static
    {
        if (!$this->responses->contains($response)) {
            $this->responses->add($response);
            $response->setPracticeSession($this);
        }
        return $this;
    }

    public function removeResponse(CandidateResponse $response): static
    {
        if ($this->responses->removeElement($response)) {
            if ($response->getPracticeSession() === $this) {
                $response->setPracticeSession(null);
            }
        }
        return $this;
    }
}
