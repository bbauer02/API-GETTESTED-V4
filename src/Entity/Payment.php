<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\PaymentMethodEnum;
use App\Enum\PaymentStatusEnum;
use App\Repository\PaymentRepository;
use App\State\PaymentCompleteProcessor;
use App\State\PaymentCreateProcessor;
use App\State\PaymentFailProcessor;
use App\State\PaymentRefundProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            security: "is_granted('PAYMENT_VIEW', object)",
            normalizationContext: ['groups' => ['payment:read']],
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiResource(
    uriTemplate: '/payments/{id}/complete',
    operations: [
        new Patch(
            security: "is_granted('PAYMENT_CREATE', object)",
            processor: PaymentCompleteProcessor::class,
            denormalizationContext: ['groups' => ['payment:transition']],
            normalizationContext: ['groups' => ['payment:read']],
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/payments/{id}/fail',
    operations: [
        new Patch(
            security: "is_granted('PAYMENT_CREATE', object)",
            processor: PaymentFailProcessor::class,
            denormalizationContext: ['groups' => ['payment:transition']],
            normalizationContext: ['groups' => ['payment:read']],
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/payments/{id}/refund',
    operations: [
        new Patch(
            security: "is_granted('PAYMENT_CREATE', object)",
            processor: PaymentRefundProcessor::class,
            denormalizationContext: ['groups' => ['payment:transition']],
            normalizationContext: ['groups' => ['payment:read']],
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/invoices/{invoiceId}/payments',
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            normalizationContext: ['groups' => ['payment:read']],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            processor: PaymentCreateProcessor::class,
            denormalizationContext: ['groups' => ['payment:write']],
            normalizationContext: ['groups' => ['payment:read']],
        ),
    ],
    uriVariables: [
        'invoiceId' => new Link(
            fromProperty: 'payments',
            fromClass: Invoice::class,
        ),
    ],
)]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['payment:read', 'invoice:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'float')]
    #[Groups(['payment:read', 'payment:write', 'invoice:read'])]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?float $amount = null;

    #[ORM\Column(length: 3)]
    #[Groups(['payment:read', 'payment:write', 'invoice:read'])]
    private string $currency = 'EUR';

    #[ORM\Column(enumType: PaymentStatusEnum::class)]
    #[Groups(['payment:read', 'invoice:read'])]
    private PaymentStatusEnum $status = PaymentStatusEnum::PENDING;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['payment:read', 'invoice:read'])]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(enumType: PaymentMethodEnum::class)]
    #[Groups(['payment:read', 'payment:write', 'invoice:read'])]
    #[Assert\NotBlank]
    private ?PaymentMethodEnum $paymentMethod = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['payment:read', 'payment:write'])]
    private ?string $stripePaymentIntentId = null;

    #[ORM\ManyToOne(targetEntity: Payment::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['payment:read'])]
    private ?Payment $refundedPayment = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payment:read'])]
    private ?Invoice $invoice = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;
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

    public function getStatus(): PaymentStatusEnum
    {
        return $this->status;
    }

    public function setStatus(PaymentStatusEnum $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getPaymentMethod(): ?PaymentMethodEnum
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodEnum $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    public function setStripePaymentIntentId(?string $stripePaymentIntentId): static
    {
        $this->stripePaymentIntentId = $stripePaymentIntentId;
        return $this;
    }

    public function getRefundedPayment(): ?Payment
    {
        return $this->refundedPayment;
    }

    public function setRefundedPayment(?Payment $refundedPayment): static
    {
        $this->refundedPayment = $refundedPayment;
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
}
