<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\LanguageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LanguageRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['language:read']],
        ),
        new Get(
            normalizationContext: ['groups' => ['language:read']],
        ),
        new Post(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['language:write']],
            normalizationContext: ['groups' => ['language:read']],
        ),
        new Patch(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['language:write']],
            normalizationContext: ['groups' => ['language:read']],
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiFilter(SearchFilter::class, properties: ['code' => 'exact', 'nameFr' => 'partial'])]
class Language
{
    #[ORM\Id]
    #[ORM\Column(length: 3)]
    #[Groups(['language:read', 'language:write', 'country:read:with-languages', 'user:read:self', 'user:read:admin'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 3)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    #[Groups(['language:read', 'language:write', 'country:read:with-languages', 'user:read:self', 'user:read:admin'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $nameOriginal = null;

    #[ORM\Column(length: 255)]
    #[Groups(['language:read', 'language:write', 'country:read:with-languages', 'user:read:self', 'user:read:admin'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $nameEn = null;

    #[ORM\Column(length: 255)]
    #[Groups(['language:read', 'language:write', 'country:read:with-languages', 'user:read:self', 'user:read:admin'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $nameFr = null;

    /** @var Collection<int, Country> */
    #[ORM\ManyToMany(targetEntity: Country::class, mappedBy: 'spokenLanguages')]
    private Collection $countries;

    public function __construct()
    {
        $this->countries = new ArrayCollection();
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getNameOriginal(): ?string
    {
        return $this->nameOriginal;
    }

    public function setNameOriginal(string $nameOriginal): static
    {
        $this->nameOriginal = $nameOriginal;
        return $this;
    }

    public function getNameEn(): ?string
    {
        return $this->nameEn;
    }

    public function setNameEn(string $nameEn): static
    {
        $this->nameEn = $nameEn;
        return $this;
    }

    public function getNameFr(): ?string
    {
        return $this->nameFr;
    }

    public function setNameFr(string $nameFr): static
    {
        $this->nameFr = $nameFr;
        return $this;
    }

    /** @return Collection<int, Country> */
    public function getCountries(): Collection
    {
        return $this->countries;
    }

    public function addCountry(Country $country): static
    {
        if (!$this->countries->contains($country)) {
            $this->countries->add($country);
            $country->addSpokenLanguage($this);
        }
        return $this;
    }

    public function removeCountry(Country $country): static
    {
        if ($this->countries->removeElement($country)) {
            $country->removeSpokenLanguage($this);
        }
        return $this;
    }
}
