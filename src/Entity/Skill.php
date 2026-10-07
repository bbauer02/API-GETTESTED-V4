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
use App\Repository\SkillRepository;
use App\State\AssessmentSkillCreateProcessor;
use App\State\ExamSkillCreateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SkillRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['skill:read']],
        ),
        new Get(
            normalizationContext: ['groups' => ['skill:read']],
        ),
        new Post(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['skill:write']],
            normalizationContext: ['groups' => ['skill:read']],
        ),
        new Patch(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['skill:write']],
            normalizationContext: ['groups' => ['skill:read']],
        ),
        new Delete(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiResource(
    uriTemplate: '/exams/{examId}/skills',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['skill:read']],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            processor: ExamSkillCreateProcessor::class,
            denormalizationContext: ['groups' => ['skill:write']],
            normalizationContext: ['groups' => ['skill:read']],
        ),
    ],
    uriVariables: [
        'examId' => new Link(
            fromProperty: 'skills',
            fromClass: Exam::class,
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/assessments/{assessmentId}/skills',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['skill:read']],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            processor: AssessmentSkillCreateProcessor::class,
            denormalizationContext: ['groups' => ['skill:write']],
            normalizationContext: ['groups' => ['skill:read']],
        ),
    ],
    uriVariables: [
        'assessmentId' => new Link(
            fromProperty: 'skills',
            fromClass: Assessment::class,
        ),
    ],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'label' => 'partial',
])]
class Skill
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['skill:read', 'exam:read', 'assessment:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['skill:read', 'skill:write', 'exam:read', 'assessment:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $label = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['skill:read', 'skill:write'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Skill::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['skill:read', 'skill:write'])]
    private ?Skill $parent = null;

    /** @var Collection<int, Skill> */
    #[ORM\OneToMany(targetEntity: Skill::class, mappedBy: 'parent')]
    #[Groups(['skill:read'])]
    private Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getParent(): ?Skill
    {
        return $this->parent;
    }

    public function setParent(?Skill $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /** @return Collection<int, Skill> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

}
