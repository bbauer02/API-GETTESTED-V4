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
use App\Repository\AssessmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AssessmentRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['assessment:read']],
        ),
        new Get(
            normalizationContext: ['groups' => ['assessment:read']],
        ),
        new Post(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['assessment:write']],
            normalizationContext: ['groups' => ['assessment:read']],
        ),
        new Patch(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['assessment:write']],
            normalizationContext: ['groups' => ['assessment:read']],
        ),
        new Delete(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiFilter(SearchFilter::class, properties: [
    'label' => 'partial',
    'ref' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['isInternal'])]
class Assessment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['assessment:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['assessment:read', 'assessment:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $label = null;

    #[ORM\Column(length: 50)]
    #[Groups(['assessment:read', 'assessment:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private ?string $ref = null;

    #[ORM\Column]
    #[Groups(['assessment:read', 'assessment:write'])]
    private bool $isInternal = false;

    #[ORM\ManyToOne(targetEntity: Assessment::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['assessment:read', 'assessment:write'])]
    private ?Assessment $parent = null;

    /** @var Collection<int, Assessment> */
    #[ORM\OneToMany(targetEntity: Assessment::class, mappedBy: 'parent')]
    #[Groups(['assessment:read'])]
    private Collection $children;

    /** @var Collection<int, Level> */
    #[ORM\ManyToMany(targetEntity: Level::class)]
    #[ORM\JoinTable(name: 'assessment_level')]
    #[Groups(['assessment:read', 'assessment:write'])]
    private Collection $levels;

    /** @var Collection<int, Skill> */
    #[ORM\ManyToMany(targetEntity: Skill::class)]
    #[ORM\JoinTable(name: 'assessment_skill')]
    #[Groups(['assessment:read', 'assessment:write'])]
    private Collection $skills;

    /** @var Collection<int, Exam> */
    #[ORM\OneToMany(targetEntity: Exam::class, mappedBy: 'assessment')]
    private Collection $exams;

    /** @var Collection<int, AssessmentOwnership> */
    #[ORM\OneToMany(targetEntity: AssessmentOwnership::class, mappedBy: 'assessment')]
    private Collection $ownerships;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->levels = new ArrayCollection();
        $this->skills = new ArrayCollection();
        $this->exams = new ArrayCollection();
        $this->ownerships = new ArrayCollection();
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

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(string $ref): static
    {
        $this->ref = $ref;
        return $this;
    }

    #[Groups(['assessment:read'])]
    public function isInternal(): bool
    {
        return $this->isInternal;
    }

    public function setIsInternal(bool $isInternal): static
    {
        $this->isInternal = $isInternal;
        return $this;
    }

    public function getParent(): ?Assessment
    {
        return $this->parent;
    }

    public function setParent(?Assessment $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /** @return Collection<int, Assessment> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /** @return Collection<int, Level> */
    public function getLevels(): Collection
    {
        return $this->levels;
    }

    public function addLevel(Level $level): static
    {
        if (!$this->levels->contains($level)) {
            $this->levels->add($level);
        }
        return $this;
    }

    public function removeLevel(Level $level): static
    {
        $this->levels->removeElement($level);
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

    /** @return Collection<int, Exam> */
    public function getExams(): Collection
    {
        return $this->exams;
    }

    /** @return Collection<int, AssessmentOwnership> */
    public function getOwnerships(): Collection
    {
        return $this->ownerships;
    }
}
