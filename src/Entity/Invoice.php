<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Embeddable\Counterparty;
use App\Enum\BusinessTypeEnum;
use App\Enum\InvoiceStatusEnum;
use App\Enum\InvoiceTypeEnum;
use App\Enum\OperationCategoryEnum;
use App\Repository\InvoiceRepository;
use App\State\InvoiceCreateProcessor;
use App\State\InvoiceIssueProcessor;
use App\State\InstituteInvoiceProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            normalizationContext: ['groups' => ['invoice:read']],
        ),
        new Get(
            security: "is_granted('INVOICE_VIEW', object)",
            normalizationContext: ['groups' => ['invoice:read']],
        ),
        new Patch(
            security: "is_granted('INVOICE_EDIT', object)",
            denormalizationContext: ['groups' => ['invoice:write']],
            normalizationContext: ['groups' => ['invoice:read']],
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiResource(
    uriTemplate: '/invoices/{id}/issue',
    operations: [
        new Patch(
            security: "is_granted('INVOICE_ISSUE', object)",
            processor: InvoiceIssueProcessor::class,
            denormalizationContext: ['groups' => ['invoice:issue']],
            normalizationContext: ['groups' => ['invoice:read']],
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/institutes/{instituteId}/invoices',
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: InstituteInvoiceProvider::class,
            normalizationContext: ['groups' => ['invoice:read']],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            processor: InvoiceCreateProcessor::class,
            denormalizationContext: ['groups' => ['invoice:write']],
            normalizationContext: ['groups' => ['invoice:read']],
            validate: false,
        ),
    ],
    uriVariables: [
        'instituteId' => new Link(
            fromProperty: 'invoices',
            fromClass: Institute::class,
        ),
    ],
)]
class Invoice
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['invoice:read', 'payment:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 50, nullable: true, unique: true)]
    #[Groups(['invoice:read'])]
    private ?string $invoiceNumber = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?\DateTimeInterface $invoiceDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?\DateTimeInterface $serviceDate = null;

    #[ORM\Embedded(class: Counterparty::class, columnPrefix: 'seller_')]
    #[Groups(['invoice:read', 'invoice:write'])]
    private Counterparty $seller;

    #[ORM\Embedded(class: Counterparty::class, columnPrefix: 'buyer_')]
    #[Groups(['invoice:read', 'invoice:write'])]
    private Counterparty $buyer;

    #[ORM\Column(enumType: InvoiceTypeEnum::class)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private InvoiceTypeEnum $invoiceType = InvoiceTypeEnum::INVOICE;

    #[ORM\Column(enumType: BusinessTypeEnum::class)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\NotBlank]
    private ?BusinessTypeEnum $businessType = null;

    #[ORM\Column(enumType: OperationCategoryEnum::class)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private OperationCategoryEnum $operationCategory = OperationCategoryEnum::SERVICE;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?\DateTimeInterface $paymentDueDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $paymentTerms = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $earlyPaymentDiscount = 'Néant';

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?float $latePaymentPenaltyRate = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?float $fixedRecoveryIndemnity = 40.0;

    #[ORM\Column(type: 'float')]
    #[Groups(['invoice:read'])]
    private float $totalHT = 0;

    #[ORM\Column(type: 'float')]
    #[Groups(['invoice:read'])]
    private float $totalTVA = 0;

    #[ORM\Column(type: 'float')]
    #[Groups(['invoice:read'])]
    private float $totalTTC = 0;

    #[ORM\Column(length: 3)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private string $currency = 'EUR';

    #[ORM\Column(enumType: InvoiceStatusEnum::class)]
    #[Groups(['invoice:read'])]
    private InvoiceStatusEnum $status = InvoiceStatusEnum::DRAFT;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $pdfPath = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['invoice:read'])]
    private ?Invoice $creditedInvoice = null;

    #[ORM\ManyToOne(targetEntity: EnrollmentSession::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?EnrollmentSession $enrollmentSession = null;

    #[ORM\ManyToOne(targetEntity: AssessmentOwnership::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?AssessmentOwnership $assessmentOwnership = null;

    #[ORM\ManyToOne(targetEntity: Institute::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['invoice:read'])]
    #[Assert\NotNull]
    private ?Institute $institute = null;

    /** @var Collection<int, InvoiceLine> */
    #[ORM\OneToMany(targetEntity: InvoiceLine::class, mappedBy: 'invoice', cascade: ['persist', 'remove'])]
    #[Groups(['invoice:read'])]
    private Collection $lines;

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'invoice', cascade: ['persist', 'remove'])]
    #[Groups(['invoice:read'])]
    private Collection $payments;

    public function __construct()
    {
        $this->seller = new Counterparty();
        $this->buyer = new Counterparty();
        $this->lines = new ArrayCollection();
        $this->payments = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(?string $invoiceNumber): static
    {
        $this->invoiceNumber = $invoiceNumber;
        return $this;
    }

    public function getInvoiceDate(): ?\DateTimeInterface
    {
        return $this->invoiceDate;
    }

    public function setInvoiceDate(?\DateTimeInterface $invoiceDate): static
    {
        $this->invoiceDate = $invoiceDate;
        return $this;
    }

    public function getServiceDate(): ?\DateTimeInterface
    {
        return $this->serviceDate;
    }

    public function setServiceDate(?\DateTimeInterface $serviceDate): static
    {
        $this->serviceDate = $serviceDate;
        return $this;
    }

    public function getSeller(): Counterparty
    {
        return $this->seller;
    }

    public function setSeller(Counterparty $seller): static
    {
        $this->seller = $seller;
        return $this;
    }

    public function getBuyer(): Counterparty
    {
        return $this->buyer;
    }

    public function setBuyer(Counterparty $buyer): static
    {
        $this->buyer = $buyer;
        return $this;
    }

    public function getInvoiceType(): InvoiceTypeEnum
    {
        return $this->invoiceType;
    }

    public function setInvoiceType(InvoiceTypeEnum $invoiceType): static
    {
        $this->invoiceType = $invoiceType;
        return $this;
    }

    public function getBusinessType(): ?BusinessTypeEnum
    {
        return $this->businessType;
    }

    public function setBusinessType(BusinessTypeEnum $businessType): static
    {
        $this->businessType = $businessType;
        return $this;
    }

    public function getOperationCategory(): OperationCategoryEnum
    {
        return $this->operationCategory;
    }

    public function setOperationCategory(OperationCategoryEnum $operationCategory): static
    {
        $this->operationCategory = $operationCategory;
        return $this;
    }

    public function getPaymentDueDate(): ?\DateTimeInterface
    {
        return $this->paymentDueDate;
    }

    public function setPaymentDueDate(?\DateTimeInterface $paymentDueDate): static
    {
        $this->paymentDueDate = $paymentDueDate;
        return $this;
    }

    public function getPaymentTerms(): ?string
    {
        return $this->paymentTerms;
    }

    public function setPaymentTerms(?string $paymentTerms): static
    {
        $this->paymentTerms = $paymentTerms;
        return $this;
    }

    public function getEarlyPaymentDiscount(): ?string
    {
        return $this->earlyPaymentDiscount;
    }

    public function setEarlyPaymentDiscount(?string $earlyPaymentDiscount): static
    {
        $this->earlyPaymentDiscount = $earlyPaymentDiscount;
        return $this;
    }

    public function getLatePaymentPenaltyRate(): ?float
    {
        return $this->latePaymentPenaltyRate;
    }

    public function setLatePaymentPenaltyRate(?float $latePaymentPenaltyRate): static
    {
        $this->latePaymentPenaltyRate = $latePaymentPenaltyRate;
        return $this;
    }

    public function getFixedRecoveryIndemnity(): ?float
    {
        return $this->fixedRecoveryIndemnity;
    }

    public function setFixedRecoveryIndemnity(?float $fixedRecoveryIndemnity): static
    {
        $this->fixedRecoveryIndemnity = $fixedRecoveryIndemnity;
        return $this;
    }

    public function getTotalHT(): float
    {
        return $this->totalHT;
    }

    public function setTotalHT(float $totalHT): static
    {
        $this->totalHT = $totalHT;
        return $this;
    }

    public function getTotalTVA(): float
    {
        return $this->totalTVA;
    }

    public function setTotalTVA(float $totalTVA): static
    {
        $this->totalTVA = $totalTVA;
        return $this;
    }

    public function getTotalTTC(): float
    {
        return $this->totalTTC;
    }

    public function setTotalTTC(float $totalTTC): static
    {
        $this->totalTTC = $totalTTC;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getStatus(): InvoiceStatusEnum
    {
        return $this->status;
    }

    public function setStatus(InvoiceStatusEnum $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(?string $pdfPath): static
    {
        $this->pdfPath = $pdfPath;
        return $this;
    }

    public function getCreditedInvoice(): ?Invoice
    {
        return $this->creditedInvoice;
    }

    public function setCreditedInvoice(?Invoice $creditedInvoice): static
    {
        $this->creditedInvoice = $creditedInvoice;
        return $this;
    }

    public function getEnrollmentSession(): ?EnrollmentSession
    {
        return $this->enrollmentSession;
    }

    public function setEnrollmentSession(?EnrollmentSession $enrollmentSession): static
    {
        $this->enrollmentSession = $enrollmentSession;
        return $this;
    }

    public function getAssessmentOwnership(): ?AssessmentOwnership
    {
        return $this->assessmentOwnership;
    }

    public function setAssessmentOwnership(?AssessmentOwnership $assessmentOwnership): static
    {
        $this->assessmentOwnership = $assessmentOwnership;
        return $this;
    }

    public function getInstitute(): ?Institute
    {
        return $this->institute;
    }

    public function setInstitute(?Institute $institute): static
    {
        $this->institute = $institute;
        return $this;
    }

    /** @return Collection<int, InvoiceLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(InvoiceLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setInvoice($this);
        }
        return $this;
    }

    public function removeLine(InvoiceLine $line): static
    {
        if ($this->lines->removeElement($line)) {
            if ($line->getInvoice() === $this) {
                $line->setInvoice(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, Payment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setInvoice($this);
        }
        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getInvoice() === $this) {
                $payment->setInvoice(null);
            }
        }
        return $this;
    }
}
