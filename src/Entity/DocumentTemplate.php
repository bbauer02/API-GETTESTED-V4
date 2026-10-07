<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\DocumentTemplateRepository;
use App\State\DocumentTemplateCreateProcessor;
use App\State\DocumentTemplateCollectionProvider;
use App\State\MasterTemplateCollectionProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentTemplateRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    shortName: 'document-templates',
    operations: [
        new Get(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            normalizationContext: ['groups' => ['document_template:read']],
        ),
        new Patch(
            security: "object.getInstitute() === null ? is_granted('ROLE_PLATFORM_ADMIN') : is_granted('INSTITUTE_EDIT', object.getInstitute())",
            denormalizationContext: ['groups' => ['document_template:write']],
            normalizationContext: ['groups' => ['document_template:read']],
        ),
        new Delete(
            security: "object.getInstitute() === null ? is_granted('ROLE_PLATFORM_ADMIN') : is_granted('INSTITUTE_EDIT', object.getInstitute())",
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/admin/document-templates',
    shortName: 'document-templates',
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            provider: MasterTemplateCollectionProvider::class,
            normalizationContext: ['groups' => ['document_template:read']],
            name: 'admin_master_templates_list',
        ),
        new Post(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['document_template:write']],
            normalizationContext: ['groups' => ['document_template:read']],
            name: 'admin_master_templates_create',
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/institutes/{instituteId}/document-templates',
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: DocumentTemplateCollectionProvider::class,
            normalizationContext: ['groups' => ['document_template:read']],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            processor: DocumentTemplateCreateProcessor::class,
            denormalizationContext: ['groups' => ['document_template:write']],
            normalizationContext: ['groups' => ['document_template:read']],
        ),
    ],
    uriVariables: [
        'instituteId' => new Link(
            fromProperty: 'documentTemplates',
            fromClass: Institute::class,
        ),
    ],
)]
class DocumentTemplate
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['document_template:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['document_template:read', 'document_template:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $label = null;

    #[ORM\ManyToOne(targetEntity: DocumentType::class, inversedBy: 'documentTemplates')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['document_template:read', 'document_template:write'])]
    #[Assert\NotNull]
    private ?DocumentType $documentType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['document_template:read', 'document_template:write'])]
    private ?string $content = null;

    #[ORM\ManyToOne(targetEntity: Institute::class, inversedBy: 'documentTemplates')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['document_template:read'])]
    private ?Institute $institute = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['document_template:read'])]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['document_template:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['document_template:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ---- Getters / Setters ----

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getDocumentType(): ?DocumentType
    {
        return $this->documentType;
    }

    public function setDocumentType(?DocumentType $documentType): static
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;
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

    public function isDefault(): bool
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
