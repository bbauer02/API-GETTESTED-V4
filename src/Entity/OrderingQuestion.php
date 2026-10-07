<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'ordering-questions',
    operations: [
        new Post(
            security: "is_granted('QUESTION_CREATE')",
            denormalizationContext: ['groups' => ['question:write']],
            normalizationContext: ['groups' => ['question:read']],
        ),
    ],
)]
#[ORM\Entity]
#[ORM\Table(name: 'ordering_question')]
class OrderingQuestion extends Question
{
    #[ORM\Column]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    private bool $shuffleOnDisplay = true;

    /** @var Collection<int, OrderingItem> */
    #[ORM\OneToMany(targetEntity: OrderingItem::class, mappedBy: 'question', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['question:read', 'question:write', 'subject:read'])]
    private Collection $orderingItems;

    public function __construct()
    {
        parent::__construct();
        $this->orderingItems = new ArrayCollection();
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

    /** @return Collection<int, OrderingItem> */
    public function getOrderingItems(): Collection
    {
        return $this->orderingItems;
    }

    public function addOrderingItem(OrderingItem $orderingItem): static
    {
        if (!$this->orderingItems->contains($orderingItem)) {
            $this->orderingItems->add($orderingItem);
            $orderingItem->setQuestion($this);
        }
        return $this;
    }

    public function removeOrderingItem(OrderingItem $orderingItem): static
    {
        if ($this->orderingItems->removeElement($orderingItem)) {
            if ($orderingItem->getQuestion() === $this) {
                $orderingItem->setQuestion(null);
            }
        }
        return $this;
    }
}
