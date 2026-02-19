<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Embeddable\Price;
use App\Repository\InstituteExamPricingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InstituteExamPricingRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            normalizationContext: ['groups' => ['exam_pricing:read']],
        ),
        new Get(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            normalizationContext: ['groups' => ['exam_pricing:read']],
        ),
        new Post(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['exam_pricing:write']],
            normalizationContext: ['groups' => ['exam_pricing:read']],
        ),
        new Patch(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['exam_pricing:write']],
            normalizationContext: ['groups' => ['exam_pricing:read']],
        ),
        new Delete(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
        ),
    ],
    paginationItemsPerPage: 30,
)]
class InstituteExamPricing
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['exam_pricing:read'])]
    private ?Uuid $id = null;

    #[ORM\Embedded(class: Price::class, columnPrefix: 'price_')]
    #[Groups(['exam_pricing:read', 'exam_pricing:write'])]
    private Price $price;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['exam_pricing:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column]
    #[Groups(['exam_pricing:read', 'exam_pricing:write'])]
    private bool $isActive = true;

    #[ORM\ManyToOne(targetEntity: Institute::class, inversedBy: 'examPricings')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['exam_pricing:read', 'exam_pricing:write'])]
    #[Assert\NotNull]
    private ?Institute $institute = null;

    #[ORM\ManyToOne(targetEntity: Exam::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['exam_pricing:read', 'exam_pricing:write'])]
    #[Assert\NotNull]
    private ?Exam $exam = null;

    public function __construct()
    {
        $this->price = new Price();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    #[Groups(['exam_pricing:read'])]
    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    public function getExam(): ?Exam
    {
        return $this->exam;
    }

    public function setExam(?Exam $exam): static
    {
        $this->exam = $exam;
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }
}
