<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Embeddable\Address;
use App\Repository\ScheduledExamRepository;
use App\State\ScheduledExamCreateProcessor;
use App\State\ScheduledExamDeleteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ScheduledExamRepository::class)]
#[ApiResource(
    shortName: 'scheduled-exams',
    operations: [
        new Get(
            normalizationContext: ['groups' => ['scheduled_exam:read']],
        ),
        new Patch(
            security: "is_granted('SCHEDULED_EXAM_EDIT', object)",
            denormalizationContext: ['groups' => ['scheduled_exam:write']],
            normalizationContext: ['groups' => ['scheduled_exam:read']],
        ),
        new Delete(
            security: "is_granted('SCHEDULED_EXAM_DELETE', object)",
            processor: ScheduledExamDeleteProcessor::class,
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiResource(
    uriTemplate: '/sessions/{sessionId}/scheduled-exams',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['scheduled_exam:read']],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            processor: ScheduledExamCreateProcessor::class,
            denormalizationContext: ['groups' => ['scheduled_exam:write']],
            normalizationContext: ['groups' => ['scheduled_exam:read']],
            validate: false,
        ),
    ],
    uriVariables: [
        'sessionId' => new Link(
            fromProperty: 'scheduledExams',
            fromClass: Session::class,
        ),
    ],
)]
class ScheduledExam
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['scheduled_exam:read', 'session:read', 'enrollment_exam:read', 'enrollment:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['scheduled_exam:read', 'scheduled_exam:write', 'session:read', 'enrollment_exam:read', 'enrollment:read'])]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['scheduled_exam:read', 'scheduled_exam:write', 'session:read', 'enrollment_exam:read'])]
    #[Assert\Length(max: 255)]
    private ?string $room = null;

    #[ORM\Embedded(class: Address::class, columnPrefix: 'address_')]
    #[Groups(['scheduled_exam:read', 'scheduled_exam:write', 'session:read', 'enrollment:read'])]
    private Address $address;

    #[ORM\ManyToOne(targetEntity: ExamCenter::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['scheduled_exam:read', 'scheduled_exam:write', 'session:read', 'enrollment:read'])]
    private ?ExamCenter $examCenter = null;

    #[ORM\ManyToOne(targetEntity: Exam::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['scheduled_exam:read', 'scheduled_exam:write', 'session:read', 'enrollment_exam:read', 'enrollment:read'])]
    #[Assert\NotNull]
    private ?Exam $exam = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'scheduledExams')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['scheduled_exam:read'])]
    #[Assert\NotNull]
    private ?Session $session = null;

    #[ORM\OneToOne(targetEntity: Subject::class, mappedBy: 'scheduledExam')]
    #[Groups(['scheduled_exam:read'])]
    private ?Subject $subject = null;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'scheduled_exam_examinator')]
    #[Groups(['scheduled_exam:read', 'scheduled_exam:write', 'session:read'])]
    private Collection $examinators;

    public function __construct()
    {
        $this->address = new Address();
        $this->examinators = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getRoom(): ?string
    {
        return $this->room;
    }

    public function setRoom(?string $room): static
    {
        $this->room = $room;
        return $this;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getExamCenter(): ?ExamCenter
    {
        return $this->examCenter;
    }

    public function setExamCenter(?ExamCenter $examCenter): static
    {
        $this->examCenter = $examCenter;
        return $this;
    }

    public function getExam(): ?Exam
    {
        return $this->exam;
    }

    public function setExam(?Exam $exam): static
    {
        $this->exam = $exam;
        return $this;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function setSession(?Session $session): static
    {
        $this->session = $session;
        return $this;
    }

    /** @return Collection<int, User> */
    public function getExaminators(): Collection
    {
        return $this->examinators;
    }

    public function addExaminator(User $user): static
    {
        if (!$this->examinators->contains($user)) {
            $this->examinators->add($user);
        }
        return $this;
    }

    public function removeExaminator(User $user): static
    {
        $this->examinators->removeElement($user);
        return $this;
    }

    public function getSubject(): ?Subject
    {
        return $this->subject;
    }

    /** Épreuve passée en ligne : un sujet verrouillé lui est associé. */
    #[Groups(['scheduled_exam:read', 'session:read', 'enrollment:read'])]
    public function isOnline(): bool
    {
        return $this->subject !== null && $this->subject->getStatus() === \App\Enum\SubjectStatusEnum::LOCKED;
    }

    public function setSubject(?Subject $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    #[Groups(['scheduled_exam:read', 'session:read'])]
    public function getExamPricing(): ?InstituteExamPricing
    {
        $institute = $this->session?->getInstitute();
        if (!$institute || !$this->exam) {
            return null;
        }
        foreach ($institute->getExamPricings() as $pricing) {
            if ($pricing->getExam()?->getId()?->equals($this->exam->getId()) && $pricing->isActive()) {
                return $pricing;
            }
        }
        return null;
    }
}
