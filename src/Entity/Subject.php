<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\SubjectModeEnum;
use App\Enum\SubjectStatusEnum;
use App\Repository\SubjectRepository;
use App\State\SubjectArchiveProcessor;
use App\State\SubjectLockProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(
            security: "is_granted('SUBJECT_VIEW', object)",
            normalizationContext: ['groups' => ['subject:read']],
        ),
        new Patch(
            security: "is_granted('SUBJECT_EDIT', object)",
            denormalizationContext: ['groups' => ['subject:write']],
            normalizationContext: ['groups' => ['subject:read']],
        ),
        new Delete(
            security: "is_granted('SUBJECT_DELETE', object)",
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            processor: \App\State\SubjectCreateProcessor::class,
            denormalizationContext: ['groups' => ['subject:write']],
            normalizationContext: ['groups' => ['subject:read']],
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiResource(
    uriTemplate: '/scheduled-exams/{scheduledExamId}/subject',
    operations: [
        new Get(
            security: "is_granted('SUBJECT_VIEW', object)",
            normalizationContext: ['groups' => ['subject:read']],
        ),
    ],
    uriVariables: [
        'scheduledExamId' => new Link(
            toProperty: 'scheduledExam',
            fromClass: \App\Entity\ScheduledExam::class,
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/subjects/{id}/lock',
    operations: [
        new Post(
            security: "is_granted('SUBJECT_LOCK', object)",
            processor: SubjectLockProcessor::class,
            normalizationContext: ['groups' => ['subject:read']],
            read: true,
            deserialize: false,
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/subjects/{id}/archive',
    operations: [
        new Post(
            security: "is_granted('SUBJECT_ARCHIVE', object)",
            processor: SubjectArchiveProcessor::class,
            normalizationContext: ['groups' => ['subject:read']],
            read: true,
            deserialize: false,
        ),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: ['status' => 'exact'])]
#[ORM\Entity(repositoryClass: SubjectRepository::class)]
#[ORM\Table(name: 'subject')]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'unique_subject_scheduled_exam', columns: ['scheduled_exam_id'])]
class Subject
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['subject:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['subject:read', 'subject:write'])]
    #[Assert\NotBlank]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['subject:read', 'subject:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: SubjectStatusEnum::class)]
    #[Groups(['subject:read', 'subject:write'])]
    private SubjectStatusEnum $status = SubjectStatusEnum::DRAFT;

    #[ORM\Column(length: 20, enumType: SubjectModeEnum::class)]
    #[Groups(['subject:read', 'subject:write'])]
    private SubjectModeEnum $mode = SubjectModeEnum::FIXED;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups(['subject:read', 'subject:write'])]
    private ?float $passingScore = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Groups(['subject:read', 'subject:write'])]
    private ?float $totalMaxPoints = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['subject:read'])]
    private ?\DateTimeImmutable $lockedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['subject:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['subject:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToOne(targetEntity: ScheduledExam::class, inversedBy: 'subject')]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    #[Groups(['subject:read', 'subject:write'])]
    private ?ScheduledExam $scheduledExam = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['subject:read'])]
    private ?User $lockedBy = null;

    #[ORM\ManyToOne(targetEntity: SubjectCompositionRule::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['subject:read', 'subject:write'])]
    private ?SubjectCompositionRule $compositionRule = null;

    /** @var Collection<int, SubjectQuestion> */
    #[ORM\OneToMany(targetEntity: SubjectQuestion::class, mappedBy: 'subject', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['subject:read'])]
    private Collection $subjectQuestions;

    public function __construct()
    {
        $this->subjectQuestions = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): SubjectStatusEnum
    {
        return $this->status;
    }

    public function setStatus(SubjectStatusEnum $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getMode(): SubjectModeEnum
    {
        return $this->mode;
    }

    public function setMode(SubjectModeEnum $mode): static
    {
        $this->mode = $mode;
        return $this;
    }

    public function getPassingScore(): ?float
    {
        return $this->passingScore;
    }

    public function setPassingScore(?float $passingScore): static
    {
        $this->passingScore = $passingScore;
        return $this;
    }

    public function getTotalMaxPoints(): ?float
    {
        return $this->totalMaxPoints;
    }

    public function setTotalMaxPoints(?float $totalMaxPoints): static
    {
        $this->totalMaxPoints = $totalMaxPoints;
        return $this;
    }

    public function getLockedAt(): ?\DateTimeImmutable
    {
        return $this->lockedAt;
    }

    public function setLockedAt(?\DateTimeImmutable $lockedAt): static
    {
        $this->lockedAt = $lockedAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getScheduledExam(): ?ScheduledExam
    {
        return $this->scheduledExam;
    }

    public function setScheduledExam(ScheduledExam $scheduledExam): static
    {
        $this->scheduledExam = $scheduledExam;
        return $this;
    }

    public function getLockedBy(): ?User
    {
        return $this->lockedBy;
    }

    public function setLockedBy(?User $lockedBy): static
    {
        $this->lockedBy = $lockedBy;
        return $this;
    }

    public function getCompositionRule(): ?SubjectCompositionRule
    {
        return $this->compositionRule;
    }

    public function setCompositionRule(?SubjectCompositionRule $compositionRule): static
    {
        $this->compositionRule = $compositionRule;
        return $this;
    }

    /** @return Collection<int, SubjectQuestion> */
    public function getSubjectQuestions(): Collection
    {
        return $this->subjectQuestions;
    }

    public function addSubjectQuestion(SubjectQuestion $subjectQuestion): static
    {
        if (!$this->subjectQuestions->contains($subjectQuestion)) {
            $this->subjectQuestions->add($subjectQuestion);
            $subjectQuestion->setSubject($this);
        }
        return $this;
    }

    public function removeSubjectQuestion(SubjectQuestion $subjectQuestion): static
    {
        if ($this->subjectQuestions->removeElement($subjectQuestion)) {
            if ($subjectQuestion->getSubject() === $this) {
                $subjectQuestion->setSubject(null);
            }
        }
        return $this;
    }
}
