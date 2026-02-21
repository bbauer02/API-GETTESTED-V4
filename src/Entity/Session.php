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
use App\Enum\SessionValidationEnum;
use App\Repository\SessionRepository;
use App\State\InstituteSessionCreateProcessor;
use App\State\InstituteSessionProvider;
use App\State\SessionEnrollmentProvider;
use App\State\SessionEnrollProcessor;
use App\State\SessionPatchProcessor;
use App\State\SessionSoftDeleteProcessor;
use App\State\SessionTransitionProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SessionRepository::class)]
#[ORM\Table(name: '`session`')]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['session:read']],
        ),
        new Get(
            normalizationContext: ['groups' => ['session:read']],
        ),
        new Patch(
            security: "is_granted('SESSION_EDIT', object)",
            denormalizationContext: ['groups' => ['session:update']],
            normalizationContext: ['groups' => ['session:read']],
            processor: SessionPatchProcessor::class,
        ),
        new Patch(
            uriTemplate: '/sessions/{id}/transition',
            security: "is_granted('SESSION_TRANSITION', object)",
            denormalizationContext: ['groups' => ['session:transition']],
            normalizationContext: ['groups' => ['session:read']],
            processor: SessionTransitionProcessor::class,
        ),
        new Delete(
            security: "is_granted('SESSION_DELETE', object)",
            processor: SessionSoftDeleteProcessor::class,
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiResource(
    uriTemplate: '/institutes/{instituteId}/sessions',
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: InstituteSessionProvider::class,
            normalizationContext: ['groups' => ['session:read']],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            processor: InstituteSessionCreateProcessor::class,
            denormalizationContext: ['groups' => ['session:write']],
            normalizationContext: ['groups' => ['session:read']],
            validate: false,
        ),
    ],
    uriVariables: [
        'instituteId' => new Link(
            fromProperty: 'sessions',
            fromClass: Institute::class,
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/sessions/{sessionId}/enroll',
    operations: [
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            processor: SessionEnrollProcessor::class,
            normalizationContext: ['groups' => ['enrollment:read']],
            output: EnrollmentSession::class,
            validate: false,
            name: 'session_enroll',
        ),
    ],
    uriVariables: [
        'sessionId' => new Link(toClass: Session::class),
    ],
)]
#[ApiResource(
    uriTemplate: '/sessions/{sessionId}/enrollments',
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: SessionEnrollmentProvider::class,
            normalizationContext: ['groups' => ['enrollment:read']],
        ),
    ],
    uriVariables: [
        'sessionId' => new Link(toClass: Session::class),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'validation' => 'exact',
])]
class Session
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['session:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(name: '`start`', type: Types::DATETIME_MUTABLE)]
    #[Groups(['session:read', 'session:write', 'session:update'])]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $start = null;

    #[ORM\Column(name: '`end`', type: Types::DATETIME_MUTABLE)]
    #[Groups(['session:read', 'session:write', 'session:update'])]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $end = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['session:read', 'session:write', 'session:update'])]
    private ?\DateTimeInterface $limitDateSubscribe = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['session:read', 'session:write', 'session:update'])]
    private ?int $placesAvailable = null;

    #[ORM\Column(enumType: SessionValidationEnum::class)]
    #[Groups(['session:read'])]
    private SessionValidationEnum $validation = SessionValidationEnum::DRAFT;

    #[ORM\ManyToOne(targetEntity: Assessment::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['session:read', 'session:write'])]
    #[Assert\NotNull]
    private ?Assessment $assessment = null;

    #[ORM\ManyToOne(targetEntity: Level::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['session:read', 'session:write'])]
    private ?Level $level = null;

    #[ORM\ManyToOne(targetEntity: Institute::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['session:read'])]
    private ?Institute $institute = null;

    /** @var Collection<int, ScheduledExam> */
    #[ORM\OneToMany(targetEntity: ScheduledExam::class, mappedBy: 'session')]
    #[Groups(['session:read'])]
    private Collection $scheduledExams;

    /** @var Collection<int, EnrollmentSession> */
    #[ORM\OneToMany(targetEntity: EnrollmentSession::class, mappedBy: 'session')]
    #[Groups(['session:read'])]
    private Collection $enrollments;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    #[Groups(['session:transition'])]
    private ?string $transition = null;

    public function __construct()
    {
        $this->scheduledExams = new ArrayCollection();
        $this->enrollments = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getStart(): ?\DateTimeInterface
    {
        return $this->start;
    }

    public function setStart(\DateTimeInterface $start): static
    {
        $this->start = $start;
        return $this;
    }

    public function getEnd(): ?\DateTimeInterface
    {
        return $this->end;
    }

    public function setEnd(\DateTimeInterface $end): static
    {
        $this->end = $end;
        return $this;
    }

    public function getLimitDateSubscribe(): ?\DateTimeInterface
    {
        return $this->limitDateSubscribe;
    }

    public function setLimitDateSubscribe(?\DateTimeInterface $limitDateSubscribe): static
    {
        $this->limitDateSubscribe = $limitDateSubscribe;
        return $this;
    }

    public function getPlacesAvailable(): ?int
    {
        return $this->placesAvailable;
    }

    public function setPlacesAvailable(?int $placesAvailable): static
    {
        $this->placesAvailable = $placesAvailable;
        return $this;
    }

    public function getValidation(): SessionValidationEnum
    {
        return $this->validation;
    }

    public function setValidation(SessionValidationEnum $validation): static
    {
        $this->validation = $validation;
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

    public function getInstitute(): ?Institute
    {
        return $this->institute;
    }

    public function setInstitute(?Institute $institute): static
    {
        $this->institute = $institute;
        return $this;
    }

    /** @return Collection<int, ScheduledExam> */
    public function getScheduledExams(): Collection
    {
        return $this->scheduledExams;
    }

    /** @return Collection<int, EnrollmentSession> */
    public function getEnrollments(): Collection
    {
        return $this->enrollments;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function getTransition(): ?string
    {
        return $this->transition;
    }

    public function setTransition(?string $transition): static
    {
        $this->transition = $transition;
        return $this;
    }
}
