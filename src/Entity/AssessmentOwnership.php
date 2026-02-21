<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\OwnershipTypeEnum;
use App\Repository\AssessmentOwnershipRepository;
use App\State\InstituteOwnershipCreateProcessor;
use App\State\InstituteOwnershipProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AssessmentOwnershipRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            normalizationContext: ['groups' => ['ownership:read']],
        ),
        new Get(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            normalizationContext: ['groups' => ['ownership:read']],
        ),
        new Post(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['ownership:write']],
            normalizationContext: ['groups' => ['ownership:read']],
        ),
        new Patch(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['ownership:write']],
            normalizationContext: ['groups' => ['ownership:read']],
        ),
        new Delete(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiResource(
    uriTemplate: '/institutes/{instituteId}/ownerships',
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: InstituteOwnershipProvider::class,
            normalizationContext: ['groups' => ['ownership:read']],
        ),
        new Post(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            read: false,
            processor: InstituteOwnershipCreateProcessor::class,
            denormalizationContext: ['groups' => ['ownership:write']],
            normalizationContext: ['groups' => ['ownership:read']],
            validationContext: ['groups' => ['ownership:create_sub']],
        ),
    ],
    uriVariables: [
        'instituteId' => new Link(
            fromProperty: 'assessmentOwnerships',
            fromClass: Institute::class,
        ),
    ],
)]
class AssessmentOwnership
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['ownership:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(enumType: OwnershipTypeEnum::class)]
    #[Groups(['ownership:read', 'ownership:write'])]
    #[Assert\NotBlank]
    private ?OwnershipTypeEnum $ownershipType = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['ownership:read', 'ownership:write'])]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $relationshipDate = null;

    #[ORM\ManyToOne(targetEntity: Institute::class, inversedBy: 'assessmentOwnerships')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['ownership:read', 'ownership:write'])]
    #[Assert\NotNull]
    private ?Institute $institute = null;

    #[ORM\ManyToOne(targetEntity: Assessment::class, inversedBy: 'ownerships')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['ownership:read', 'ownership:write'])]
    #[Assert\NotNull(groups: ['Default', 'ownership:create_sub'])]
    private ?Assessment $assessment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['ownership:read', 'ownership:write'])]
    private ?User $user = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getOwnershipType(): ?OwnershipTypeEnum
    {
        return $this->ownershipType;
    }

    public function setOwnershipType(OwnershipTypeEnum $ownershipType): static
    {
        $this->ownershipType = $ownershipType;
        return $this;
    }

    public function getRelationshipDate(): ?\DateTimeInterface
    {
        return $this->relationshipDate;
    }

    public function setRelationshipDate(\DateTimeInterface $relationshipDate): static
    {
        $this->relationshipDate = $relationshipDate;
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

    public function getAssessment(): ?Assessment
    {
        return $this->assessment;
    }

    public function setAssessment(?Assessment $assessment): static
    {
        $this->assessment = $assessment;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
}
