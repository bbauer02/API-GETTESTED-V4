<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'fill-blank-questions',
    operations: [
        new Post(
            security: "is_granted('QUESTION_CREATE')",
            denormalizationContext: ['groups' => ['question:write']],
            normalizationContext: ['groups' => ['question:read']],
        ),
    ],
)]
#[ORM\Entity]
#[ORM\Table(name: 'fill_blank_question')]
class FillBlankQuestion extends Question
{
    #[ORM\Column(length: 10)]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    private string $blankSymbol = '___';

    /** @var Collection<int, BlankSlot> */
    #[ORM\OneToMany(targetEntity: BlankSlot::class, mappedBy: 'question', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    private Collection $blankSlots;

    public function __construct()
    {
        parent::__construct();
        $this->blankSlots = new ArrayCollection();
    }

    public function getBlankSymbol(): string
    {
        return $this->blankSymbol;
    }

    public function setBlankSymbol(string $blankSymbol): static
    {
        $this->blankSymbol = $blankSymbol;
        return $this;
    }

    /** @return Collection<int, BlankSlot> */
    public function getBlankSlots(): Collection
    {
        return $this->blankSlots;
    }

    public function addBlankSlot(BlankSlot $blankSlot): static
    {
        if (!$this->blankSlots->contains($blankSlot)) {
            $this->blankSlots->add($blankSlot);
            $blankSlot->setQuestion($this);
        }
        return $this;
    }

    public function removeBlankSlot(BlankSlot $blankSlot): static
    {
        if ($this->blankSlots->removeElement($blankSlot)) {
            if ($blankSlot->getQuestion() === $this) {
                $blankSlot->setQuestion(null);
            }
        }
        return $this;
    }
}
