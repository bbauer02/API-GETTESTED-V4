<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\SubjectCompositionRuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['composition_rule:read']],
        ),
        new Get(
            normalizationContext: ['groups' => ['composition_rule:read']],
        ),
        new Post(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['composition_rule:write']],
            normalizationContext: ['groups' => ['composition_rule:read']],
        ),
        new Patch(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['composition_rule:write']],
            normalizationContext: ['groups' => ['composition_rule:read']],
        ),
        new Delete(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ORM\Entity(repositoryClass: SubjectCompositionRuleRepository::class)]
#[ORM\Table(name: 'subject_composition_rule')]
class SubjectCompositionRule
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['composition_rule:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['composition_rule:read', 'composition_rule:write'])]
    #[Assert\NotNull]
    #[Assert\Positive]
    private ?int $totalQuestions = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['composition_rule:read', 'composition_rule:write'])]
    #[Assert\PositiveOrZero]
    private int $totalSeedQuestions = 0;

    #[ORM\Column(type: 'integer')]
    #[Groups(['composition_rule:read', 'composition_rule:write'])]
    #[Assert\NotNull]
    #[Assert\Positive]
    private ?int $durationMinutes = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['composition_rule:read', 'composition_rule:write'])]
    private ?array $skillDistribution = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['composition_rule:read', 'composition_rule:write'])]
    private ?array $difficultyDistribution = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['composition_rule:read', 'composition_rule:write'])]
    private int $minSpacingDaysGlobal = 90;

    #[ORM\Column(type: 'integer')]
    #[Groups(['composition_rule:read', 'composition_rule:write'])]
    private int $minSpacingDaysSameInstitute = 180;

    #[ORM\Column(type: 'float')]
    #[Groups(['composition_rule:read', 'composition_rule:write'])]
    private float $maxExposureRate = 0.35;

    #[ORM\ManyToOne(targetEntity: Assessment::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['composition_rule:read', 'composition_rule:write'])]
    private ?Assessment $assessment = null;

    #[ORM\ManyToOne(targetEntity: Level::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['composition_rule:read', 'composition_rule:write'])]
    private ?Level $level = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTotalQuestions(): ?int
    {
        return $this->totalQuestions;
    }

    public function setTotalQuestions(int $totalQuestions): static
    {
        $this->totalQuestions = $totalQuestions;
        return $this;
    }

    public function getTotalSeedQuestions(): int
    {
        return $this->totalSeedQuestions;
    }

    public function setTotalSeedQuestions(int $totalSeedQuestions): static
    {
        $this->totalSeedQuestions = $totalSeedQuestions;
        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;
        return $this;
    }

    public function getSkillDistribution(): ?array
    {
        return $this->skillDistribution;
    }

    public function setSkillDistribution(?array $skillDistribution): static
    {
        $this->skillDistribution = $skillDistribution;
        return $this;
    }

    public function getDifficultyDistribution(): ?array
    {
        return $this->difficultyDistribution;
    }

    public function setDifficultyDistribution(?array $difficultyDistribution): static
    {
        $this->difficultyDistribution = $difficultyDistribution;
        return $this;
    }

    public function getMinSpacingDaysGlobal(): int
    {
        return $this->minSpacingDaysGlobal;
    }

    public function setMinSpacingDaysGlobal(int $minSpacingDaysGlobal): static
    {
        $this->minSpacingDaysGlobal = $minSpacingDaysGlobal;
        return $this;
    }

    public function getMinSpacingDaysSameInstitute(): int
    {
        return $this->minSpacingDaysSameInstitute;
    }

    public function setMinSpacingDaysSameInstitute(int $minSpacingDaysSameInstitute): static
    {
        $this->minSpacingDaysSameInstitute = $minSpacingDaysSameInstitute;
        return $this;
    }

    public function getMaxExposureRate(): float
    {
        return $this->maxExposureRate;
    }

    public function setMaxExposureRate(float $maxExposureRate): static
    {
        $this->maxExposureRate = $maxExposureRate;
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
}
