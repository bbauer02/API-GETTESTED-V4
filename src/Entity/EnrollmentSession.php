<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\EnrollmentTransferInput;
use App\Enum\EnrollmentStatusEnum;
use App\Repository\EnrollmentSessionRepository;
use App\State\EnrollmentCancelProcessor;
use ApiPlatform\Metadata\Link;
use App\State\EnrollmentTransferProcessor;
use App\State\InstituteEnrollmentProvider;
use App\State\MyEnrollmentsProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EnrollmentSessionRepository::class)]
#[ApiResource(
    shortName: 'enrollment-sessions',
    operations: [
        new Get(
            security: "is_granted('ENROLLMENT_VIEW', object)",
            normalizationContext: ['groups' => ['enrollment:read'], 'skip_null_values' => false],
        ),
        new Patch(
            security: "is_granted('ENROLLMENT_EDIT', object)",
            denormalizationContext: ['groups' => ['enrollment:update']],
            normalizationContext: ['groups' => ['enrollment:read'], 'skip_null_values' => false],
        ),
        new Delete(
            security: "is_granted('ENROLLMENT_CANCEL', object)",
            processor: EnrollmentCancelProcessor::class,
        ),
        new Post(
            uriTemplate: '/enrollment-sessions/{id}/transfer',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            input: EnrollmentTransferInput::class,
            processor: EnrollmentTransferProcessor::class,
            normalizationContext: ['groups' => ['enrollment:read'], 'skip_null_values' => false],
            status: 200,
            name: 'enrollment_transfer',
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiResource(
    uriTemplate: '/users/me/enrollments',
    shortName: 'enrollment-sessions',
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyEnrollmentsProvider::class,
            normalizationContext: ['groups' => ['enrollment:read'], 'skip_null_values' => false],
            name: 'my_enrollments',
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/institutes/{instituteId}/enrollments',
    shortName: 'enrollment-sessions',
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: InstituteEnrollmentProvider::class,
            normalizationContext: ['groups' => ['enrollment:read'], 'skip_null_values' => false],
            name: 'institute_enrollments',
        ),
    ],
    uriVariables: [
        'instituteId' => new Link(toClass: Institute::class),
    ],
)]
class EnrollmentSession
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['enrollment:read', 'session:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 15, unique: true, nullable: true)]
    #[Groups(['enrollment:read', 'session:read'])]
    private ?string $referenceNumber = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['enrollment:read', 'session:read'])]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $registrationDate = null;

    /** Une inscription annulée est conservée (historique, factures, avoirs) mais ne compte plus. */
    #[ORM\Column(length: 20, enumType: EnrollmentStatusEnum::class, options: ['default' => 'ACTIVE'])]
    #[Groups(['enrollment:read', 'session:read'])]
    private EnrollmentStatusEnum $status = EnrollmentStatusEnum::ACTIVE;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['enrollment:read', 'session:read'])]
    private ?\DateTimeInterface $cancelledAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['enrollment:read', 'enrollment:update', 'session:read'])]
    private ?string $information = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['enrollment:read', 'session:read'])]
    #[Assert\NotNull]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'enrollments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['enrollment:read'])]
    #[Assert\NotNull]
    private ?Session $session = null;

    /** @var Collection<int, EnrollmentExam> */
    #[ORM\OneToMany(targetEntity: EnrollmentExam::class, mappedBy: 'enrollmentSession')]
    #[Groups(['enrollment:read', 'session:read'])]
    private Collection $enrollmentExams;

    /** @var Collection<int, Invoice> */
    #[ORM\OneToMany(targetEntity: Invoice::class, mappedBy: 'enrollmentSession')]
    #[Groups(['enrollment:read', 'session:read'])]
    private Collection $invoices;

    public function __construct()
    {
        $this->enrollmentExams = new ArrayCollection();
        $this->invoices = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getReferenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(?string $referenceNumber): static
    {
        $this->referenceNumber = $referenceNumber;
        return $this;
    }

    public function getRegistrationDate(): ?\DateTimeInterface
    {
        return $this->registrationDate;
    }

    public function setRegistrationDate(\DateTimeInterface $registrationDate): static
    {
        $this->registrationDate = $registrationDate;
        return $this;
    }

    public function getStatus(): EnrollmentStatusEnum
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === EnrollmentStatusEnum::ACTIVE;
    }

    public function getCancelledAt(): ?\DateTimeInterface
    {
        return $this->cancelledAt;
    }

    public function cancel(\DateTimeInterface $at = new \DateTime()): static
    {
        $this->status = EnrollmentStatusEnum::CANCELLED;
        $this->cancelledAt = $at;
        return $this;
    }

    public function getInformation(): ?string
    {
        return $this->information;
    }

    public function setInformation(?string $information): static
    {
        $this->information = $information;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function setSession(?Session $session): static
    {
        $this->session = $session;
        return $this;
    }

    /** @return Collection<int, EnrollmentExam> */
    public function getEnrollmentExams(): Collection
    {
        return $this->enrollmentExams;
    }

    /** @return Collection<int, Invoice> */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }
}
