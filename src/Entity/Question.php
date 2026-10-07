<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Enum\QuestionStatusEnum;
use App\Repository\QuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[ORM\Table(name: 'question')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap([
    'mcq'        => MCQQuestion::class,
    'fill_blank' => FillBlankQuestion::class,
    'highlight'  => HighlightQuestion::class,
    'ordering'   => OrderingQuestion::class,
    'matching'   => MatchingQuestion::class,
])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['question:read']],
        ),
        new Get(
            normalizationContext: ['groups' => ['question:read']],
        ),
        new Patch(
            security: "is_granted('QUESTION_EDIT', object)",
            denormalizationContext: ['groups' => ['question:write']],
            normalizationContext: ['groups' => ['question:read']],
        ),
        new Delete(
            security: "is_granted('QUESTION_DELETE', object)",
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiFilter(SearchFilter::class, properties: [
    'label' => 'partial',
    'text' => 'partial',
    'assessment' => 'exact',
    'level' => 'exact',
    'status' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'timesAdministered', 'successRate'])]
abstract class Question
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['question:read', 'subject:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $label = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    #[Assert\NotBlank]
    private ?string $text = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    private ?string $instruction = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['question:read', 'question:write'])]
    #[Assert\Positive]
    private ?int $duration = null;

    #[ORM\Column(type: 'float')]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    #[Assert\Positive]
    private float $maxPoints = 1.0;

    #[ORM\Column(enumType: QuestionStatusEnum::class)]
    #[Groups(['question:read'])]
    private QuestionStatusEnum $status = QuestionStatusEnum::DRAFT;

    // --- IRT Parameters (nullable until calibrated) ---

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['question:read:admin'])]
    private ?float $irtDifficulty = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['question:read:admin'])]
    private ?float $irtDiscrimination = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['question:read:admin'])]
    private ?float $irtGuessing = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['question:read:admin'])]
    private ?float $irtStdError = null;

    // --- Calibration stats ---

    #[ORM\Column]
    #[Groups(['question:read'])]
    private int $timesAdministered = 0;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['question:read'])]
    private ?float $successRate = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['question:read'])]
    private ?int $avgResponseTimeMs = null;

    // --- Lifecycle ---

    #[ORM\Column]
    #[Groups(['question:read'])]
    private bool $isSeedItem = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['question:read'])]
    private ?\DateTimeInterface $retiredAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['question:read'])]
    private ?string $retiredReason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['question:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['question:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    // --- Relations ---

    #[ORM\ManyToOne(targetEntity: Assessment::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['question:read', 'question:write'])]
    #[Assert\NotNull]
    private ?Assessment $assessment = null;

    #[ORM\ManyToOne(targetEntity: Level::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    #[Assert\NotNull]
    private ?Level $level = null;

    /** @var Collection<int, Skill> */
    #[ORM\ManyToMany(targetEntity: Skill::class)]
    #[ORM\JoinTable(name: 'question_skill')]
    #[Groups(['question:read', 'question:write'])]
    private Collection $skills;

    /** @var Collection<int, Media> */
    #[ORM\OneToMany(targetEntity: Media::class, mappedBy: 'question', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    private Collection $medias;

    public function __construct()
    {
        $this->skills = new ArrayCollection();
        $this->medias = new ArrayCollection();
    }

    // --- Lifecycle callbacks ---

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // --- Discriminator accessor ---

    #[Groups(['question:read', 'subject:read'])]
    public function getType(): string
    {
        return match (static::class) {
            MCQQuestion::class => 'mcq',
            FillBlankQuestion::class => 'fill_blank',
            HighlightQuestion::class => 'highlight',
            OrderingQuestion::class => 'ordering',
            MatchingQuestion::class => 'matching',
            default => 'unknown',
        };
    }

    // --- Getters & Setters ---

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function getInstruction(): ?string
    {
        return $this->instruction;
    }

    public function setInstruction(?string $instruction): static
    {
        $this->instruction = $instruction;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getMaxPoints(): float
    {
        return $this->maxPoints;
    }

    public function setMaxPoints(float $maxPoints): static
    {
        $this->maxPoints = $maxPoints;
        return $this;
    }

    public function getStatus(): QuestionStatusEnum
    {
        return $this->status;
    }

    public function setStatus(QuestionStatusEnum $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getIrtDifficulty(): ?float
    {
        return $this->irtDifficulty;
    }

    public function setIrtDifficulty(?float $irtDifficulty): static
    {
        $this->irtDifficulty = $irtDifficulty;
        return $this;
    }

    public function getIrtDiscrimination(): ?float
    {
        return $this->irtDiscrimination;
    }

    public function setIrtDiscrimination(?float $irtDiscrimination): static
    {
        $this->irtDiscrimination = $irtDiscrimination;
        return $this;
    }

    public function getIrtGuessing(): ?float
    {
        return $this->irtGuessing;
    }

    public function setIrtGuessing(?float $irtGuessing): static
    {
        $this->irtGuessing = $irtGuessing;
        return $this;
    }

    public function getIrtStdError(): ?float
    {
        return $this->irtStdError;
    }

    public function setIrtStdError(?float $irtStdError): static
    {
        $this->irtStdError = $irtStdError;
        return $this;
    }

    public function getTimesAdministered(): int
    {
        return $this->timesAdministered;
    }

    public function setTimesAdministered(int $timesAdministered): static
    {
        $this->timesAdministered = $timesAdministered;
        return $this;
    }

    public function getSuccessRate(): ?float
    {
        return $this->successRate;
    }

    public function setSuccessRate(?float $successRate): static
    {
        $this->successRate = $successRate;
        return $this;
    }

    public function getAvgResponseTimeMs(): ?int
    {
        return $this->avgResponseTimeMs;
    }

    public function setAvgResponseTimeMs(?int $avgResponseTimeMs): static
    {
        $this->avgResponseTimeMs = $avgResponseTimeMs;
        return $this;
    }

    public function isSeedItem(): bool
    {
        return $this->isSeedItem;
    }

    public function setIsSeedItem(bool $isSeedItem): static
    {
        $this->isSeedItem = $isSeedItem;
        return $this;
    }

    public function getRetiredAt(): ?\DateTimeInterface
    {
        return $this->retiredAt;
    }

    public function setRetiredAt(?\DateTimeInterface $retiredAt): static
    {
        $this->retiredAt = $retiredAt;
        return $this;
    }

    public function getRetiredReason(): ?string
    {
        return $this->retiredReason;
    }

    public function setRetiredReason(?string $retiredReason): static
    {
        $this->retiredReason = $retiredReason;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
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

    /** @return Collection<int, Skill> */
    public function getSkills(): Collection
    {
        return $this->skills;
    }

    public function addSkill(Skill $skill): static
    {
        if (!$this->skills->contains($skill)) {
            $this->skills->add($skill);
        }
        return $this;
    }

    public function removeSkill(Skill $skill): static
    {
        $this->skills->removeElement($skill);
        return $this;
    }

    /** @return Collection<int, Media> */
    public function getMedias(): Collection
    {
        return $this->medias;
    }

    public function addMedia(Media $media): static
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->setQuestion($this);
        }
        return $this;
    }

    public function removeMedia(Media $media): static
    {
        if ($this->medias->removeElement($media)) {
            if ($media->getQuestion() === $this) {
                $media->setQuestion(null);
            }
        }
        return $this;
    }
}
