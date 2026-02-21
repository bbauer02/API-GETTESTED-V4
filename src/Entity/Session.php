<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\SessionValidationEnum;
use App\Repository\SessionRepository;
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
        new Post(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['session:write']],
            normalizationContext: ['groups' => ['session:read']],
        ),
        new Patch(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['session:write']],
            normalizationContext: ['groups' => ['session:read']],
        ),
        new Delete(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
        ),
    ],
    paginationItemsPerPage: 30,
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
    #[Groups(['session:read', 'session:write'])]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $start = null;

    #[ORM\Column(name: '`end`', type: Types::DATETIME_MUTABLE)]
    #[Groups(['session:read', 'session:write'])]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $end = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['session:read', 'session:write'])]
    private ?\DateTimeInterface $limitDateSubscribe = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['session:read', 'session:write'])]
    private ?int $placesAvailable = null;

    #[ORM\Column(enumType: SessionValidationEnum::class)]
    #[Groups(['session:read', 'session:write'])]
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
    #[Groups(['session:read', 'session:write'])]
    #[Assert\NotNull]
    private ?Institute $institute = null;

    /** @var Collection<int, ScheduledExam> */
    #[ORM\OneToMany(targetEntity: ScheduledExam::class, mappedBy: 'session')]
    #[Groups(['session:read'])]
    private Collection $scheduledExams;

    /** @var Collection<int, EnrollmentSession> */
    #[ORM\OneToMany(targetEntity: EnrollmentSession::class, mappedBy: 'session')]
    #[Groups(['session:read'])]
    private Collection $enrollments;

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
}
