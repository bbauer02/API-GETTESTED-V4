<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class MatchingPair
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['question:read', 'question:write'])]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['question:read', 'question:write'])]
    #[Assert\NotBlank]
    private ?string $leftText = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['question:read', 'question:write'])]
    #[Assert\NotBlank]
    private ?string $rightText = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['question:read', 'question:write'])]
    private int $position = 0;

    #[ORM\ManyToOne(targetEntity: MatchingQuestion::class, inversedBy: 'matchingPairs')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['question:read', 'question:write'])]
    private ?MatchingQuestion $question = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getLeftText(): ?string
    {
        return $this->leftText;
    }

    public function setLeftText(string $leftText): static
    {
        $this->leftText = $leftText;
        return $this;
    }

    public function getRightText(): ?string
    {
        return $this->rightText;
    }

    public function setRightText(string $rightText): static
    {
        $this->rightText = $rightText;
        return $this;
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

    public function getQuestion(): ?MatchingQuestion
    {
        return $this->question;
    }

    public function setQuestion(?MatchingQuestion $question): static
    {
        $this->question = $question;
        return $this;
    }
}
