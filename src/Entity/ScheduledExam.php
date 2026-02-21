<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Embeddable\Address;
use App\Repository\ScheduledExamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ScheduledExamRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['scheduled_exam:read']],
        ),
        new Get(
            normalizationContext: ['groups' => ['scheduled_exam:read']],
        ),
        new Post(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['scheduled_exam:write']],
            normalizationContext: ['groups' => ['scheduled_exam:read']],
        ),
        new Patch(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['scheduled_exam:write']],
            normalizationContext: ['groups' => ['scheduled_exam:read']],
        ),
        new Delete(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
        ),
    ],
    paginationItemsPerPage: 30,
)]
class ScheduledExam
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['scheduled_exam:read', 'session:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['scheduled_exam:read', 'scheduled_exam:write', 'session:read'])]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['scheduled_exam:read', 'scheduled_exam:write', 'session:read'])]
    #[Assert\Length(max: 255)]
    private ?string $room = null;

    #[ORM\Embedded(class: Address::class, columnPrefix: 'address_')]
    #[Groups(['scheduled_exam:read', 'scheduled_exam:write', 'session:read'])]
    private Address $address;

    #[ORM\ManyToOne(targetEntity: Exam::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['scheduled_exam:read', 'scheduled_exam:write', 'session:read'])]
    #[Assert\NotNull]
    private ?Exam $exam = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'scheduledExams')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['scheduled_exam:read', 'scheduled_exam:write'])]
    #[Assert\NotNull]
    private ?Session $session = null;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'scheduled_exam_examinator')]
    #[Groups(['scheduled_exam:read', 'scheduled_exam:write'])]
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
