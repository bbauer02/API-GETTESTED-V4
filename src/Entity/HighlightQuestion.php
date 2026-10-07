<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'highlight-questions',
    operations: [
        new Post(
            security: "is_granted('QUESTION_CREATE')",
            denormalizationContext: ['groups' => ['question:write']],
            normalizationContext: ['groups' => ['question:read']],
        ),
    ],
)]
#[ORM\Entity]
#[ORM\Table(name: 'highlight_question')]
class HighlightQuestion extends Question
{
    /** @var Collection<int, HighlightZone> */
    #[ORM\OneToMany(targetEntity: HighlightZone::class, mappedBy: 'question', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    private Collection $highlightZones;

    public function __construct()
    {
        parent::__construct();
        $this->highlightZones = new ArrayCollection();
    }

    /** @return Collection<int, HighlightZone> */
    public function getHighlightZones(): Collection
    {
        return $this->highlightZones;
    }

    public function addHighlightZone(HighlightZone $highlightZone): static
    {
        if (!$this->highlightZones->contains($highlightZone)) {
            $this->highlightZones->add($highlightZone);
            $highlightZone->setQuestion($this);
        }
        return $this;
    }

    public function removeHighlightZone(HighlightZone $highlightZone): static
    {
        if ($this->highlightZones->removeElement($highlightZone)) {
            if ($highlightZone->getQuestion() === $this) {
                $highlightZone->setQuestion(null);
            }
        }
        return $this;
    }
}
