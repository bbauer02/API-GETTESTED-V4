<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\CountryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CountryRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['country:read']],
        ),
        new Get(
            normalizationContext: ['groups' => ['country:read', 'country:read:with-languages']],
        ),
        new Post(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['country:write']],
            normalizationContext: ['groups' => ['country:read']],
        ),
        new Patch(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['country:write']],
            normalizationContext: ['groups' => ['country:read']],
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiFilter(SearchFilter::class, properties: ['code' => 'exact', 'nameFr' => 'partial', 'nameEn' => 'partial'])]
class Country
{
    #[ORM\Id]
    #[ORM\Column(length: 2)]
    #[Groups(['country:read', 'country:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 2)]
    private ?string $code = null;

    #[ORM\Column(length: 3, unique: true)]
    #[Groups(['country:read', 'country:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 3)]
    private ?string $alpha3 = null;

    #[ORM\Column(length: 255)]
    #[Groups(['country:read', 'country:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $nameOriginal = null;

    #[ORM\Column(length: 255)]
    #[Groups(['country:read', 'country:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $nameEn = null;

    #[ORM\Column(length: 255)]
    #[Groups(['country:read', 'country:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $nameFr = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['country:read', 'country:write'])]
    private ?string $flag = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['country:read', 'country:write'])]
    #[Assert\Length(max: 100)]
    private ?string $demonymFr = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['country:read', 'country:write'])]
    #[Assert\Length(max: 100)]
    private ?string $demonymEn = null;

    /** @var Collection<int, Language> */
    #[ORM\ManyToMany(targetEntity: Language::class, inversedBy: 'countries')]
    #[ORM\JoinTable(name: 'country_language')]
    #[ORM\JoinColumn(name: 'country_code', referencedColumnName: 'code')]
    #[ORM\InverseJoinColumn(name: 'language_code', referencedColumnName: 'code')]
    #[Groups(['country:read:with-languages', 'country:write'])]
    private Collection $spokenLanguages;

    public function __construct()
    {
        $this->spokenLanguages = new ArrayCollection();
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

    public function getAlpha3(): ?string
    {
        return $this->alpha3;
    }

    public function setAlpha3(string $alpha3): static
    {
        $this->alpha3 = $alpha3;
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

    public function getFlag(): ?string
    {
        return $this->flag;
    }

    public function setFlag(?string $flag): static
    {
        $this->flag = $flag;
        return $this;
    }

    public function getDemonymFr(): ?string
    {
        return $this->demonymFr;
    }

    public function setDemonymFr(?string $demonymFr): static
    {
        $this->demonymFr = $demonymFr;
        return $this;
    }

    public function getDemonymEn(): ?string
    {
        return $this->demonymEn;
    }

    public function setDemonymEn(?string $demonymEn): static
    {
        $this->demonymEn = $demonymEn;
        return $this;
    }

    /** @return Collection<int, Language> */
    public function getSpokenLanguages(): Collection
    {
        return $this->spokenLanguages;
    }

    public function addSpokenLanguage(Language $language): static
    {
        if (!$this->spokenLanguages->contains($language)) {
            $this->spokenLanguages->add($language);
        }
        return $this;
    }

    public function removeSpokenLanguage(Language $language): static
    {
        $this->spokenLanguages->removeElement($language);
        return $this;
    }
}
