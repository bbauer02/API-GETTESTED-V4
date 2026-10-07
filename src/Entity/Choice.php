<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Choice
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
    private ?string $text = null;

    #[ORM\Column(type: 'float')]
    #[Groups(['question:read', 'question:write'])]
    private float $weight = 0.0;

    #[ORM\Column(type: 'integer')]
    #[Groups(['question:read', 'question:write'])]
    private int $position = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['question:read'])]
    private ?string $feedback = null;

    #[ORM\ManyToOne(targetEntity: MCQQuestion::class, inversedBy: 'choices')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['question:read', 'question:write'])]
    private ?MCQQuestion $question = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function setWeight(float $weight): static
    {
        $this->weight = $weight;
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

    public function getFeedback(): ?string
    {
        return $this->feedback;
    }

    public function setFeedback(?string $feedback): static
    {
        $this->feedback = $feedback;
        return $this;
    }

    public function getQuestion(): ?MCQQuestion
    {
        return $this->question;
    }

    public function setQuestion(?MCQQuestion $question): static
    {
        $this->question = $question;
        return $this;
    }
}
