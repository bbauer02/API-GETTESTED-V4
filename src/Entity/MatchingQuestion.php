<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'matching-questions',
    operations: [
        new Post(
            security: "is_granted('QUESTION_CREATE')",
            denormalizationContext: ['groups' => ['question:write']],
            normalizationContext: ['groups' => ['question:read']],
        ),
    ],
)]
#[ORM\Entity]
#[ORM\Table(name: 'matching_question')]
class MatchingQuestion extends Question
{
    #[ORM\Column]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    private bool $shuffleOnDisplay = true;

    /** @var Collection<int, MatchingPair> */
    #[ORM\OneToMany(targetEntity: MatchingPair::class, mappedBy: 'question', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    private Collection $matchingPairs;

    public function __construct()
    {
        parent::__construct();
        $this->matchingPairs = new ArrayCollection();
    }

    public function isShuffleOnDisplay(): bool
    {
        return $this->shuffleOnDisplay;
    }

    public function setShuffleOnDisplay(bool $shuffleOnDisplay): static
    {
        $this->shuffleOnDisplay = $shuffleOnDisplay;
        return $this;
    }

    /** @return Collection<int, MatchingPair> */
    public function getMatchingPairs(): Collection
    {
        return $this->matchingPairs;
    }

    public function addMatchingPair(MatchingPair $matchingPair): static
    {
        if (!$this->matchingPairs->contains($matchingPair)) {
            $this->matchingPairs->add($matchingPair);
            $matchingPair->setQuestion($this);
        }
        return $this;
    }

    public function removeMatchingPair(MatchingPair $matchingPair): static
    {
        if ($this->matchingPairs->removeElement($matchingPair)) {
            if ($matchingPair->getQuestion() === $this) {
                $matchingPair->setQuestion(null);
            }
        }
        return $this;
    }
}
