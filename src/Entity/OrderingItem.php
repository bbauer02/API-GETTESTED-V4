<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class OrderingItem
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

    #[ORM\Column(type: 'integer')]
    #[Groups(['question:read', 'question:write'])]
    #[Assert\NotNull]
    private int $correctPosition = 0;

    #[ORM\ManyToOne(targetEntity: OrderingQuestion::class, inversedBy: 'orderingItems')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['question:read', 'question:write'])]
    private ?OrderingQuestion $question = null;

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

    public function getCorrectPosition(): int
    {
        return $this->correctPosition;
    }

    public function setCorrectPosition(int $correctPosition): static
    {
        $this->correctPosition = $correctPosition;
        return $this;
    }

    public function getQuestion(): ?OrderingQuestion
    {
        return $this->question;
    }

    public function setQuestion(?OrderingQuestion $question): static
    {
        $this->question = $question;
        return $this;
    }
}
