<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class HighlightZone
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
    private int $startIndex = 0;

    #[ORM\Column(type: 'integer')]
    #[Groups(['question:read', 'question:write'])]
    #[Assert\NotNull]
    private int $endIndex = 0;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['question:read', 'question:write'])]
    #[Assert\NotNull]
    private bool $isCorrect = false;

    #[ORM\ManyToOne(targetEntity: HighlightQuestion::class, inversedBy: 'highlightZones')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['question:read', 'question:write'])]
    private ?HighlightQuestion $question = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getStartIndex(): int
    {
        return $this->startIndex;
    }

    public function setStartIndex(int $startIndex): static
    {
        $this->startIndex = $startIndex;
        return $this;
    }

    public function getEndIndex(): int
    {
        return $this->endIndex;
    }

    public function setEndIndex(int $endIndex): static
    {
        $this->endIndex = $endIndex;
        return $this;
    }

    public function isCorrect(): bool
    {
        return $this->isCorrect;
    }

    public function setIsCorrect(bool $isCorrect): static
    {
        $this->isCorrect = $isCorrect;
        return $this;
    }

    public function getQuestion(): ?HighlightQuestion
    {
        return $this->question;
    }

    public function setQuestion(?HighlightQuestion $question): static
    {
        $this->question = $question;
        return $this;
    }
}
