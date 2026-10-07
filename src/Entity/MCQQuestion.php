<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'mcq-questions',
    operations: [
        new Post(
            security: "is_granted('QUESTION_CREATE')",
            denormalizationContext: ['groups' => ['question:write']],
            normalizationContext: ['groups' => ['question:read']],
        ),
    ],
)]
#[ORM\Entity]
#[ORM\Table(name: 'mcq_question')]
class MCQQuestion extends Question
{
    #[ORM\Column]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    private bool $isMultipleAnswer = false;

    #[ORM\Column]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    private bool $shuffleChoices = true;

    /** @var Collection<int, Choice> */
    #[ORM\OneToMany(targetEntity: Choice::class, mappedBy: 'question', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    private Collection $choices;

    public function __construct()
    {
        parent::__construct();
        $this->choices = new ArrayCollection();
    }

    public function isMultipleAnswer(): bool
    {
        return $this->isMultipleAnswer;
    }

    public function setIsMultipleAnswer(bool $isMultipleAnswer): static
    {
        $this->isMultipleAnswer = $isMultipleAnswer;
        return $this;
    }

    public function isShuffleChoices(): bool
    {
        return $this->shuffleChoices;
    }

    public function setShuffleChoices(bool $shuffleChoices): static
    {
        $this->shuffleChoices = $shuffleChoices;
        return $this;
    }

    /** @return Collection<int, Choice> */
    public function getChoices(): Collection
    {
        return $this->choices;
    }

    public function addChoice(Choice $choice): static
    {
        if (!$this->choices->contains($choice)) {
            $this->choices->add($choice);
            $choice->setQuestion($this);
        }
        return $this;
    }

    public function removeChoice(Choice $choice): static
    {
        if ($this->choices->removeElement($choice)) {
            if ($choice->getQuestion() === $this) {
                $choice->setQuestion(null);
            }
        }
        return $this;
    }
}
