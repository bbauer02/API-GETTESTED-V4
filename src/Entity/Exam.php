<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Embeddable\Price;
use App\Repository\ExamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExamRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['exam:read']],
        ),
        new Get(
            normalizationContext: ['groups' => ['exam:read']],
        ),
        new Post(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['exam:write']],
            normalizationContext: ['groups' => ['exam:read']],
        ),
        new Patch(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['exam:write']],
            normalizationContext: ['groups' => ['exam:read']],
        ),
        new Delete(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiFilter(SearchFilter::class, properties: [
    'label' => 'partial',
])]
#[ApiFilter(BooleanFilter::class, properties: ['isWritten', 'isOption'])]
class Exam
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['exam:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['exam:read', 'exam:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $label = null;

    #[ORM\Column]
    #[Groups(['exam:read', 'exam:write'])]
    private bool $isWritten = false;

    #[ORM\Column]
    #[Groups(['exam:read', 'exam:write'])]
    private bool $isOption = false;

    #[ORM\Column(nullable: true)]
    #[Groups(['exam:read', 'exam:write'])]
    private ?int $coeff = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['exam:read', 'exam:write'])]
    private ?int $nbrQuestions = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['exam:read', 'exam:write'])]
    private ?int $duration = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['exam:read', 'exam:write'])]
    private ?int $successScore = null;

    #[ORM\ManyToOne(targetEntity: Assessment::class, inversedBy: 'exams')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['exam:read', 'exam:write'])]
    #[Assert\NotNull]
    private ?Assessment $assessment = null;

    #[ORM\ManyToOne(targetEntity: Level::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['exam:read', 'exam:write'])]
    private ?Level $level = null;

    /** @var Collection<int, Skill> */
    #[ORM\ManyToMany(targetEntity: Skill::class)]
    #[ORM\JoinTable(name: 'exam_skill')]
    #[Groups(['exam:read', 'exam:write'])]
    private Collection $skills;

    #[ORM\Embedded(class: Price::class, columnPrefix: 'price_')]
    #[Groups(['exam:read', 'exam:write'])]
    private Price $price;

    public function __construct()
    {
        $this->skills = new ArrayCollection();
        $this->price = new Price();
    }

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

    #[Groups(['exam:read'])]
    public function isWritten(): bool
    {
        return $this->isWritten;
    }

    public function setIsWritten(bool $isWritten): static
    {
        $this->isWritten = $isWritten;
        return $this;
    }

    #[Groups(['exam:read'])]
    public function isOption(): bool
    {
        return $this->isOption;
    }

    public function setIsOption(bool $isOption): static
    {
        $this->isOption = $isOption;
        return $this;
    }

    public function getCoeff(): ?int
    {
        return $this->coeff;
    }

    public function setCoeff(?int $coeff): static
    {
        $this->coeff = $coeff;
        return $this;
    }

    public function getNbrQuestions(): ?int
    {
        return $this->nbrQuestions;
    }

    public function setNbrQuestions(?int $nbrQuestions): static
    {
        $this->nbrQuestions = $nbrQuestions;
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

    public function getSuccessScore(): ?int
    {
        return $this->successScore;
    }

    public function setSuccessScore(?int $successScore): static
    {
        $this->successScore = $successScore;
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

    public function getPrice(): Price
    {
        return $this->price;
    }

    public function setPrice(Price $price): static
    {
        $this->price = $price;
        return $this;
    }
}
