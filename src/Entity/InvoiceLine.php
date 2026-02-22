<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\InvoiceLineRepository;
use App\State\InvoiceLineCreateProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InvoiceLineRepository::class)]
#[ApiResource(
    operations: [
        new Patch(
            security: "is_granted('INVOICE_LINE_EDIT', object)",
            denormalizationContext: ['groups' => ['invoice_line:write']],
            normalizationContext: ['groups' => ['invoice_line:read']],
        ),
        new Delete(
            security: "is_granted('INVOICE_LINE_DELETE', object)",
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiResource(
    uriTemplate: '/invoices/{invoiceId}/lines',
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            normalizationContext: ['groups' => ['invoice_line:read']],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            processor: InvoiceLineCreateProcessor::class,
            denormalizationContext: ['groups' => ['invoice_line:write']],
            normalizationContext: ['groups' => ['invoice_line:read']],
        ),
    ],
    uriVariables: [
        'invoiceId' => new Link(
            fromProperty: 'lines',
            fromClass: Invoice::class,
        ),
    ],
)]
class InvoiceLine
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['invoice:read', 'invoice_line:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['invoice:read', 'invoice_line:read', 'invoice_line:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $label = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['invoice:read', 'invoice_line:read', 'invoice_line:write'])]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['invoice:read', 'invoice_line:read', 'invoice_line:write'])]
    private int $quantity = 1;

    #[ORM\Column(type: 'float')]
    #[Groups(['invoice:read', 'invoice_line:read', 'invoice_line:write'])]
    #[Assert\NotBlank]
    private ?float $unitPriceHT = null;

    #[ORM\Column(type: 'float')]
    #[Groups(['invoice:read', 'invoice_line:read', 'invoice_line:write'])]
    private float $tvaRate = 20.0;

    #[ORM\Column(type: 'float')]
    #[Groups(['invoice:read', 'invoice_line:read'])]
    private float $tvaAmount = 0;

    #[ORM\Column(type: 'float')]
    #[Groups(['invoice:read', 'invoice_line:read'])]
    private float $totalHT = 0;

    #[ORM\Column(type: 'float')]
    #[Groups(['invoice:read', 'invoice_line:read'])]
    private float $totalTTC = 0;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['invoice_line:read'])]
    private ?Invoice $invoice = null;

    #[ORM\ManyToOne(targetEntity: Exam::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['invoice:read', 'invoice_line:read', 'invoice_line:write'])]
    private ?Exam $exam = null;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getUnitPriceHT(): ?float
    {
        return $this->unitPriceHT;
    }

    public function setUnitPriceHT(float $unitPriceHT): static
    {
        $this->unitPriceHT = $unitPriceHT;
        return $this;
    }

    public function getTvaRate(): float
    {
        return $this->tvaRate;
    }

    public function setTvaRate(float $tvaRate): static
    {
        $this->tvaRate = $tvaRate;
        return $this;
    }

    public function getTvaAmount(): float
    {
        return $this->tvaAmount;
    }

    public function setTvaAmount(float $tvaAmount): static
    {
        $this->tvaAmount = $tvaAmount;
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

    public function getTotalTTC(): float
    {
        return $this->totalTTC;
    }

    public function setTotalTTC(float $totalTTC): static
    {
        $this->totalTTC = $totalTTC;
        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function getExam(): ?Exam
    {
        return $this->exam;
    }

    public function setExam(?Exam $exam): static
    {
        $this->exam = $exam;
        return $this;
    }

    public function computeAmounts(): void
    {
        $this->totalHT = round($this->quantity * ($this->unitPriceHT ?? 0), 2);
        $this->tvaAmount = round($this->totalHT * $this->tvaRate / 100, 2);
        $this->totalTTC = round($this->totalHT + $this->tvaAmount, 2);
    }
}
