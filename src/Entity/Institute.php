<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Embeddable\Address;
use App\Entity\Invoice;
use App\Interface\ContactableInterface;
use App\Repository\InstituteRepository;
use App\State\InstituteCreateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InstituteRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['institute:read']],
        ),
        new Get(
            normalizationContext: ['groups' => ['institute:read']],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            denormalizationContext: ['groups' => ['institute:write']],
            normalizationContext: ['groups' => ['institute:read']],
            processor: InstituteCreateProcessor::class,
        ),
        new Patch(
            security: "is_granted('INSTITUTE_EDIT', object)",
            denormalizationContext: ['groups' => ['institute:write']],
            normalizationContext: ['groups' => ['institute:read']],
        ),
        new Delete(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiFilter(SearchFilter::class, properties: [
    'label' => 'partial',
])]
class Institute implements ContactableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['institute:read', 'session:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['institute:read', 'institute:write', 'session:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $label = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['institute:read', 'institute:write'])]
    #[Assert\Length(max: 255)]
    private ?string $siteweb = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['institute:read', 'institute:write'])]
    private ?array $socialNetworks = null;

    #[ORM\Embedded(class: Address::class, columnPrefix: 'address_')]
    #[Groups(['institute:read', 'institute:write'])]
    private Address $address;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['institute:read', 'institute:write'])]
    #[Assert\Length(max: 50)]
    private ?string $vatNumber = null;

    #[ORM\Column(length: 9, nullable: true)]
    #[Groups(['institute:read', 'institute:write'])]
    #[Assert\Length(max: 9)]
    private ?string $siren = null;

    #[ORM\Column(length: 14, nullable: true)]
    #[Groups(['institute:read', 'institute:write'])]
    #[Assert\Length(max: 14)]
    private ?string $siret = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['institute:read', 'institute:write'])]
    #[Assert\Length(max: 50)]
    private ?string $legalForm = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['institute:read', 'institute:write'])]
    #[Assert\Length(max: 50)]
    private ?string $shareCapital = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['institute:read', 'institute:write'])]
    #[Assert\Length(max: 100)]
    private ?string $rcsCity = null;

    #[ORM\OneToOne(mappedBy: 'institute', targetEntity: StripeAccount::class, cascade: ['persist', 'remove'])]
    #[Groups(['institute:read'])]
    private ?StripeAccount $stripeAccount = null;

    /** @var Collection<int, InstituteMembership> */
    #[ORM\OneToMany(targetEntity: InstituteMembership::class, mappedBy: 'institute', cascade: ['persist', 'remove'])]
    private Collection $memberships;

    /** @var Collection<int, AssessmentOwnership> */
    #[ORM\OneToMany(targetEntity: AssessmentOwnership::class, mappedBy: 'institute', cascade: ['remove'])]
    private Collection $assessmentOwnerships;

    /** @var Collection<int, Session> */
    #[ORM\OneToMany(targetEntity: Session::class, mappedBy: 'institute', cascade: ['remove'])]
    private Collection $sessions;

    /** @var Collection<int, InstituteExamPricing> */
    #[ORM\OneToMany(targetEntity: InstituteExamPricing::class, mappedBy: 'institute', cascade: ['remove'])]
    private Collection $examPricings;

    /** @var Collection<int, Invoice> */
    #[ORM\OneToMany(targetEntity: Invoice::class, mappedBy: 'institute', cascade: ['remove'])]
    private Collection $invoices;

    public function __construct()
    {
        $this->address = new Address();
        $this->memberships = new ArrayCollection();
        $this->assessmentOwnerships = new ArrayCollection();
        $this->sessions = new ArrayCollection();
        $this->examPricings = new ArrayCollection();
        $this->invoices = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getSiteweb(): ?string
    {
        return $this->siteweb;
    }

    public function setSiteweb(?string $siteweb): static
    {
        $this->siteweb = $siteweb;
        return $this;
    }

    public function getSocialNetworks(): ?array
    {
        return $this->socialNetworks;
    }

    public function setSocialNetworks(?array $socialNetworks): static
    {
        $this->socialNetworks = $socialNetworks;
        return $this;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): static
    {
        $this->address = $address;
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

    public function getStripeAccount(): ?StripeAccount
    {
        return $this->stripeAccount;
    }

    public function setStripeAccount(?StripeAccount $stripeAccount): static
    {
        if ($stripeAccount !== null && $stripeAccount->getInstitute() !== $this) {
            $stripeAccount->setInstitute($this);
        }
        $this->stripeAccount = $stripeAccount;
        return $this;
    }

    /** @return Collection<int, InstituteMembership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(InstituteMembership $membership): static
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setInstitute($this);
        }
        return $this;
    }

    public function removeMembership(InstituteMembership $membership): static
    {
        if ($this->memberships->removeElement($membership)) {
            if ($membership->getInstitute() === $this) {
                $membership->setInstitute(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, AssessmentOwnership> */
    public function getAssessmentOwnerships(): Collection
    {
        return $this->assessmentOwnerships;
    }

    /** @return Collection<int, Session> */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    /** @return Collection<int, InstituteExamPricing> */
    public function getExamPricings(): Collection
    {
        return $this->examPricings;
    }

    /** @return Collection<int, Invoice> */
    public function getInvoices(): Collection
    {
        return $this->invoices;
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

    // ContactableInterface
    public function getName(): string
    {
        return $this->label ?? '';
    }

    public function getContactAddress(): ?string
    {
        return $this->address->getAddress1();
    }

    public function getContactZipcode(): ?string
    {
        return $this->address->getZipcode();
    }

    public function getContactCity(): ?string
    {
        return $this->address->getCity();
    }

    public function getContactCountry(): ?string
    {
        return $this->address->getCountryCode();
    }
}
