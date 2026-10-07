<?php

namespace App\Entity;

use App\Enum\AbilityEstimateSourceEnum;
use App\Repository\AbilityEstimateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AbilityEstimateRepository::class)]
#[ORM\Table(name: 'ability_estimate')]
#[ORM\Index(columns: ['estimated_at'], name: 'idx_ability_estimated_at')]
class AbilityEstimate
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['ability:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'float')]
    #[Groups(['ability:read'])]
    private float $theta;

    #[ORM\Column(type: 'float')]
    #[Groups(['ability:read'])]
    private float $stdError;

    #[ORM\Column(length: 50)]
    #[Groups(['ability:read'])]
    private string $estimatedLevel;

    #[ORM\Column(enumType: AbilityEstimateSourceEnum::class)]
    #[Groups(['ability:read'])]
    private AbilityEstimateSourceEnum $source;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['ability:read'])]
    private ?array $skillBreakdown = null;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['ability:read'])]
    private \DateTime $estimatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['ability:read'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Assessment::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['ability:read'])]
    private ?Assessment $assessment = null;

    #[ORM\ManyToOne(targetEntity: Level::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['ability:read'])]
    private ?Level $level = null;

    #[ORM\ManyToOne(targetEntity: PracticeSession::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['ability:read'])]
    private ?PracticeSession $practiceSession = null;

    #[ORM\ManyToOne(targetEntity: EnrollmentExam::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['ability:read'])]
    private ?EnrollmentExam $enrollmentExam = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTheta(): float
    {
        return $this->theta;
    }

    public function setTheta(float $theta): static
    {
        $this->theta = $theta;
        return $this;
    }

    public function getStdError(): float
    {
        return $this->stdError;
    }

    public function setStdError(float $stdError): static
    {
        $this->stdError = $stdError;
        return $this;
    }

    public function getEstimatedLevel(): string
    {
        return $this->estimatedLevel;
    }

    public function setEstimatedLevel(string $estimatedLevel): static
    {
        $this->estimatedLevel = $estimatedLevel;
        return $this;
    }

    public function getSource(): AbilityEstimateSourceEnum
    {
        return $this->source;
    }

    public function setSource(AbilityEstimateSourceEnum $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function getSkillBreakdown(): ?array
    {
        return $this->skillBreakdown;
    }

    public function setSkillBreakdown(?array $skillBreakdown): static
    {
        $this->skillBreakdown = $skillBreakdown;
        return $this;
    }

    public function getEstimatedAt(): \DateTime
    {
        return $this->estimatedAt;
    }

    public function setEstimatedAt(\DateTime $estimatedAt): static
    {
        $this->estimatedAt = $estimatedAt;
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

    public function getPracticeSession(): ?PracticeSession
    {
        return $this->practiceSession;
    }

    public function setPracticeSession(?PracticeSession $practiceSession): static
    {
        $this->practiceSession = $practiceSession;
        return $this;
    }

    public function getEnrollmentExam(): ?EnrollmentExam
    {
        return $this->enrollmentExam;
    }

    public function setEnrollmentExam(?EnrollmentExam $enrollmentExam): static
    {
        $this->enrollmentExam = $enrollmentExam;
        return $this;
    }
}
