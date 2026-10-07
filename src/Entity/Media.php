<?php

namespace App\Entity;

use App\Enum\MediaTypeEnum;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Media
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(enumType: MediaTypeEnum::class)]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    #[Assert\NotNull]
    private ?MediaTypeEnum $type = null;

    #[ORM\Column(length: 500)]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private ?string $url = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    #[Assert\Length(max: 255)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    private int $position = 0;

    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: 'medias')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    private ?Question $question = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getType(): ?MediaTypeEnum
    {
        return $this->type;
    }

    public function setType(MediaTypeEnum $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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
