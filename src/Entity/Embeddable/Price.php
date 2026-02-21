<?php

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Embeddable]
class Price
{
    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['price:read', 'price:write', 'exam:read', 'exam:write', 'exam_pricing:read', 'exam_pricing:write', 'session:read'])]
    private ?float $amount = null;

    #[ORM\Column(length: 3, nullable: true)]
    #[Groups(['price:read', 'price:write', 'exam:read', 'exam:write', 'exam_pricing:read', 'exam_pricing:write', 'session:read'])]
    private ?string $currency = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['price:read', 'price:write', 'exam:read', 'exam:write', 'exam_pricing:read', 'exam_pricing:write', 'session:read'])]
    private ?float $tva = null;

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(?float $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getTva(): ?float
    {
        return $this->tva;
    }

    public function setTva(?float $tva): static
    {
        $this->tva = $tva;
        return $this;
    }
}
