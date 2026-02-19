<?php

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Embeddable]
class Address
{
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['address:read', 'address:write'])]
    private ?string $address1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['address:read', 'address:write'])]
    private ?string $address2 = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['address:read', 'address:write'])]
    private ?string $zipcode = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['address:read', 'address:write'])]
    private ?string $city = null;

    #[ORM\Column(length: 2, nullable: true)]
    #[Groups(['address:read', 'address:write'])]
    private ?string $countryCode = null;

    public function getAddress1(): ?string
    {
        return $this->address1;
    }

    public function setAddress1(?string $address1): static
    {
        $this->address1 = $address1;
        return $this;
    }

    public function getAddress2(): ?string
    {
        return $this->address2;
    }

    public function setAddress2(?string $address2): static
    {
        $this->address2 = $address2;
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

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;
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
}
