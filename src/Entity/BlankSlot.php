<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class BlankSlot
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['question:read', 'question:write'])]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['question:read', 'question:write'])]
    #[Assert\NotNull]
    private int $position = 0;

    #[ORM\Column(type: 'json')]
    #[Groups(['question:read', 'question:write'])]
    #[Assert\NotNull]
    private array $acceptedAnswers = [];

    #[ORM\Column(type: 'boolean')]
    #[Groups(['question:read', 'question:write'])]
    private bool $caseSensitive = false;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['question:read', 'question:write'])]
    private bool $accentSensitive = false;

    #[ORM\ManyToOne(targetEntity: FillBlankQuestion::class, inversedBy: 'blankSlots')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['question:read', 'question:write'])]
    private ?FillBlankQuestion $question = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getAcceptedAnswers(): array
    {
        return $this->acceptedAnswers;
    }

    public function setAcceptedAnswers(array $acceptedAnswers): static
    {
        $this->acceptedAnswers = $acceptedAnswers;
        return $this;
    }

    public function isCaseSensitive(): bool
    {
        return $this->caseSensitive;
    }

    public function setCaseSensitive(bool $caseSensitive): static
    {
        $this->caseSensitive = $caseSensitive;
        return $this;
    }

    public function isAccentSensitive(): bool
    {
        return $this->accentSensitive;
    }

    public function setAccentSensitive(bool $accentSensitive): static
    {
        $this->accentSensitive = $accentSensitive;
        return $this;
    }

    public function getQuestion(): ?FillBlankQuestion
    {
        return $this->question;
    }

    public function setQuestion(?FillBlankQuestion $question): static
    {
        $this->question = $question;
        return $this;
    }
}
