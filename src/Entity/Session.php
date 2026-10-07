<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\EnrollmentStatusEnum;
use App\Enum\SessionStatusEnum;
use App\Repository\SessionRepository;
use App\State\InstituteSessionCreateProcessor;
use App\State\InstituteSessionProvider;
use App\State\SessionEnrollmentProvider;
use App\State\SessionEnrollProcessor;
use App\State\SessionItemProvider;
use App\State\SessionPatchProcessor;
use App\State\SessionSoftDeleteProcessor;
use App\State\SessionTransitionProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SessionRepository::class)]
#[ORM\Table(name: '`session`')]
// Liste publique (statut + date), filtre de suppression logique appliqué à chaque requête
#[ORM\Index(name: 'idx_session_status_start', columns: ['status', 'start'])]
#[ORM\Index(name: 'idx_session_deleted_at', columns: ['deleted_at'])]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['session:read']],
        ),
        // Le détail embarque les inscrits ; les listes n'ont que leur nombre (enrollmentsCount)
        new Get(
            provider: SessionItemProvider::class,
            normalizationContext: ['groups' => ['session:read', 'session:read:enrollments']],
        ),
        new Patch(
            security: "is_granted('SESSION_EDIT', object)",
            denormalizationContext: ['groups' => ['session:update']],
            normalizationContext: ['groups' => ['session:read', 'session:read:enrollments']],
            processor: SessionPatchProcessor::class,
        ),
        new Patch(
            uriTemplate: '/sessions/{id}/transition',
            security: "is_granted('SESSION_TRANSITION', object)",
            denormalizationContext: ['groups' => ['session:transition']],
            normalizationContext: ['groups' => ['session:read', 'session:read:enrollments']],
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
            normalizationContext: ['groups' => ['enrollment:read'], 'skip_null_values' => false],
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
            normalizationContext: ['groups' => ['enrollment:read'], 'skip_null_values' => false],
        ),
    ],
    uriVariables: [
        'sessionId' => new Link(toClass: Session::class),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'status' => 'exact',
    'assessment' => 'exact',
    'level' => 'exact',
    'institute' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['start', 'limitDateSubscribe'], arguments: ['orderParameterName' => 'order'])]
class Session
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['session:read', 'enrollment:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(name: '`start`', type: Types::DATETIME_MUTABLE)]
    #[Groups(['session:read', 'session:write', 'session:update', 'enrollment:read'])]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $start = null;

    #[ORM\Column(name: '`end`', type: Types::DATETIME_MUTABLE)]
    #[Groups(['session:read', 'session:write', 'session:update', 'enrollment:read'])]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $end = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['session:read', 'session:write', 'session:update', 'session:transition', 'enrollment:read'])]
    private ?\DateTimeInterface $limitDateSubscribe = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['session:read', 'session:write', 'session:update', 'enrollment:read'])]
    private ?int $placesAvailable = null;

    #[ORM\Column(enumType: SessionStatusEnum::class)]
    #[Groups(['session:read', 'enrollment:read'])]
    private SessionStatusEnum $status = SessionStatusEnum::DRAFT;

    #[ORM\ManyToOne(targetEntity: Assessment::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['session:read', 'session:write', 'enrollment:read'])]
    #[Assert\NotNull]
    private ?Assessment $assessment = null;

    #[ORM\ManyToOne(targetEntity: Level::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['session:read', 'session:write', 'enrollment:read'])]
    private ?Level $level = null;

    #[ORM\ManyToOne(targetEntity: Institute::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['session:read', 'enrollment:read'])]
    private ?Institute $institute = null;

    /** @var Collection<int, ScheduledExam> */
    #[ORM\OneToMany(targetEntity: ScheduledExam::class, mappedBy: 'session')]
    #[Groups(['session:read', 'enrollment:read'])]
    private Collection $scheduledExams;

    /** @var Collection<int, EnrollmentSession> */
    #[ORM\OneToMany(targetEntity: EnrollmentSession::class, mappedBy: 'session', fetch: 'EXTRA_LAZY')]
    #[Groups(['session:read:enrollments'])]
    private Collection $enrollments;

    /** @var Collection<int, SessionDocumentPublication> */
    #[ORM\OneToMany(targetEntity: SessionDocumentPublication::class, mappedBy: 'session', cascade: ['remove'])]
    #[Groups(['session:read', 'enrollment:read'])]
    private Collection $documentPublications;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    /** Date du verrouillage automatique (date limite d'inscription dépassée), null si verrouillage manuel. */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['session:read'])]
    private ?\DateTimeInterface $lockedAutomaticallyAt = null;

    #[Groups(['session:transition'])]
    private ?string $transition = null;

    /** Erreurs de remboursement Stripe remontées lors d'une annulation (non persisté). */
    #[Groups(['session:read'])]
    private ?array $refundErrors = null;

    public function __construct()
    {
        $this->scheduledExams = new ArrayCollection();
        $this->enrollments = new ArrayCollection();
        $this->documentPublications = new ArrayCollection();
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

    public function getStatus(): SessionStatusEnum
    {
        return $this->status;
    }

    public function setStatus(SessionStatusEnum $status): static
    {
        $this->status = $status;
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

    /** @return Collection<int, EnrollmentSession> inscriptions non annulées */
    public function getActiveEnrollments(): Collection
    {
        // EXTRA_LAZY : un count() sur ce résultat est une requête COUNT, sans charger les inscriptions
        return $this->enrollments->matching(
            Criteria::create()->where(Criteria::expr()->eq('status', EnrollmentStatusEnum::ACTIVE))
        );
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

    public function getLockedAutomaticallyAt(): ?\DateTimeInterface
    {
        return $this->lockedAutomaticallyAt;
    }

    public function setLockedAutomaticallyAt(?\DateTimeInterface $lockedAutomaticallyAt): static
    {
        $this->lockedAutomaticallyAt = $lockedAutomaticallyAt;
        return $this;
    }

    #[Groups(['session:read'])]
    public function isAutoLocked(): bool
    {
        return $this->lockedAutomaticallyAt !== null && $this->status === SessionStatusEnum::LOCKED;
    }

    public function getRefundErrors(): ?array
    {
        return $this->refundErrors;
    }

    public function setRefundErrors(?array $refundErrors): static
    {
        $this->refundErrors = $refundErrors;
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

    /** @return Collection<int, SessionDocumentPublication> */
    public function getDocumentPublications(): Collection
    {
        return $this->documentPublications;
    }
}
