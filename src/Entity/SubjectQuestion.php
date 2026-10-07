<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\SubjectQuestionRepository;
use App\State\SubjectQuestionCreateProcessor;
use App\State\SubjectQuestionReorderProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    uriTemplate: '/subjects/{subjectId}/questions',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['subject:read']],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            processor: SubjectQuestionCreateProcessor::class,
            denormalizationContext: ['groups' => ['subject:write']],
            normalizationContext: ['groups' => ['subject:read']],
        ),
    ],
    uriVariables: [
        'subjectId' => new Link(
            fromProperty: 'subjectQuestions',
            fromClass: \App\Entity\Subject::class,
        ),
    ],
)]
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['subject:read']],
        ),
        new Patch(
            security: "is_granted('SUBJECT_EDIT', object.getSubject())",
            denormalizationContext: ['groups' => ['subject:write']],
            normalizationContext: ['groups' => ['subject:read']],
        ),
        new Delete(
            security: "is_granted('SUBJECT_EDIT', object.getSubject())",
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/subjects/{subjectId}/questions/reorder',
    operations: [
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            processor: SubjectQuestionReorderProcessor::class,
            denormalizationContext: ['groups' => ['subject:write']],
            normalizationContext: ['groups' => ['subject:read']],
        ),
    ],
    uriVariables: [
        'subjectId' => new Link(
            fromProperty: 'subjectQuestions',
            fromClass: \App\Entity\Subject::class,
        ),
    ],
)]
#[ORM\Entity(repositoryClass: SubjectQuestionRepository::class)]
#[ORM\Table(name: 'subject_question')]
#[ORM\UniqueConstraint(name: 'unique_subject_question', columns: ['subject_id', 'question_id'])]
#[ORM\UniqueConstraint(name: 'unique_subject_position', columns: ['subject_id', 'position'])]
class SubjectQuestion
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['subject:read', 'subject:write'])]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['subject:read', 'subject:write'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private ?int $position = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['subject:read', 'subject:write'])]
    private bool $isSeed = false;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['subject:read', 'subject:write'])]
    private ?float $pointsOverride = null;

    #[ORM\ManyToOne(targetEntity: Subject::class, inversedBy: 'subjectQuestions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['subject_question:read'])]
    private ?Subject $subject = null;

    #[ORM\ManyToOne(targetEntity: Question::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['subject:read', 'subject:write'])]
    private ?Question $question = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function isSeed(): bool
    {
        return $this->isSeed;
    }

    public function setIsSeed(bool $isSeed): static
    {
        $this->isSeed = $isSeed;
        return $this;
    }

    public function getPointsOverride(): ?float
    {
        return $this->pointsOverride;
    }

    public function setPointsOverride(?float $pointsOverride): static
    {
        $this->pointsOverride = $pointsOverride;
        return $this;
    }

    public function getSubject(): ?Subject
    {
        return $this->subject;
    }

    public function setSubject(?Subject $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): static
    {
        $this->question = $question;
        return $this;
    }
}
