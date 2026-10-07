<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Embeddable\Address;
use App\Repository\ExamCenterRepository;
use App\State\ExamCenterPersistProcessor;
use App\State\InstituteExamCenterCreateProcessor;
use App\State\InstituteExamCenterProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Centre d'examen (salle / lieu) d'un institut, réutilisable sur les épreuves planifiées.
 */
#[ORM\Entity(repositoryClass: ExamCenterRepository::class)]
#[ApiResource(
    shortName: 'exam-centers',
    operations: [
        new Get(
            security: "is_granted('EXAM_CENTER_VIEW', object)",
            normalizationContext: ['groups' => ['exam_center:read']],
        ),
        new Patch(
            security: "is_granted('EXAM_CENTER_EDIT', object)",
            denormalizationContext: ['groups' => ['exam_center:write']],
            normalizationContext: ['groups' => ['exam_center:read']],
            processor: ExamCenterPersistProcessor::class,
        ),
        new Delete(
            security: "is_granted('EXAM_CENTER_EDIT', object)",
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiResource(
    uriTemplate: '/institutes/{instituteId}/exam-centers',
    shortName: 'exam-centers',
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: InstituteExamCenterProvider::class,
            normalizationContext: ['groups' => ['exam_center:read']],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            processor: InstituteExamCenterCreateProcessor::class,
            denormalizationContext: ['groups' => ['exam_center:write']],
            normalizationContext: ['groups' => ['exam_center:read']],
        ),
    ],
    uriVariables: [
        'instituteId' => new Link(
            fromProperty: 'examCenters',
            fromClass: Institute::class,
        ),
    ],
)]
class ExamCenter
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['exam_center:read', 'scheduled_exam:read', 'session:read', 'enrollment:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['exam_center:read', 'exam_center:write', 'scheduled_exam:read', 'session:read', 'enrollment:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $label = null;

    #[ORM\Embedded(class: Address::class, columnPrefix: 'address_')]
    #[Groups(['exam_center:read', 'exam_center:write', 'scheduled_exam:read', 'session:read', 'enrollment:read'])]
    private Address $address;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['exam_center:read', 'exam_center:write'])]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['exam_center:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Institute::class, inversedBy: 'examCenters')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Institute $institute = null;

    public function __construct()
    {
        $this->address = new Address();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    /** Accesseur pour la sérialisation sous le nom "isDefault". */
    public function getIsDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
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
}
