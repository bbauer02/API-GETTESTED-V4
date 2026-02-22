<?php

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Embeddable]
class Counterparty
{
    #[ORM\Column(length: 255)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $city = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $zipcode = null;

    #[ORM\Column(length: 5, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $countryCode = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $vatNumber = null;

    #[ORM\Column(length: 9, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $siren = null;

    #[ORM\Column(length: 14, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $siret = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $legalForm = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $shareCapital = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $rcsCity = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getZipcode(): ?string
    {
        return $this->zipcode;
    }

    public function setZipcode(?string $zipcode): static
    {
        $this->zipcode = $zipcode;
        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): static
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): static
    {
        $this->vatNumber = $vatNumber;
        return $this;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(?string $siren): static
    {
        $this->siren = $siren;
        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): static
    {
        $this->siret = $siret;
        return $this;
    }

    public function getLegalForm(): ?string
    {
        return $this->legalForm;
    }

    public function setLegalForm(?string $legalForm): static
    {
        $this->legalForm = $legalForm;
        return $this;
    }

    public function getShareCapital(): ?string
    {
        return $this->shareCapital;
    }

    public function setShareCapital(?string $shareCapital): static
    {
        $this->shareCapital = $shareCapital;
        return $this;
    }

    public function getRcsCity(): ?string
    {
        return $this->rcsCity;
    }

    public function setRcsCity(?string $rcsCity): static
    {
        $this->rcsCity = $rcsCity;
        return $this;
    }
}
