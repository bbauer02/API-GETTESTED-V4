<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Repository\SessionDocumentPublicationRepository;
use App\State\SessionDocumentPublicationCreateProcessor;
use App\State\SessionDocumentPublicationProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SessionDocumentPublicationRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_session_doctype', columns: ['session_id', 'document_type_id'])]
#[ApiResource(
    shortName: 'session-document-publications',
    operations: [
        new Delete(
            security: "is_granted('SESSION_EDIT', object.getSession())",
        ),
    ],
)]
#[ApiResource(
    uriTemplate: '/sessions/{sessionId}/document-publications',
    shortName: 'session-document-publications',
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: SessionDocumentPublicationProvider::class,
            normalizationContext: ['groups' => ['publication:read']],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            processor: SessionDocumentPublicationCreateProcessor::class,
            denormalizationContext: ['groups' => ['publication:write']],
            normalizationContext: ['groups' => ['publication:read']],
            validate: false,
        ),
    ],
    uriVariables: [
        'sessionId' => new Link(toClass: Session::class),
    ],
)]
class SessionDocumentPublication
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['publication:read', 'session:read', 'enrollment:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'documentPublications')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['publication:read'])]
    private ?Session $session = null;

    #[ORM\ManyToOne(targetEntity: DocumentType::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['publication:read', 'publication:write', 'session:read', 'enrollment:read'])]
    private ?DocumentType $documentType = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['publication:read', 'session:read', 'enrollment:read'])]
    private bool $isAutoPublished = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['publication:read', 'session:read', 'enrollment:read'])]
    private ?\DateTimeImmutable $publishedAt = null;

    /** Disponibilité calculée (DocumentAccessService), non persistée. */
    #[Groups(['publication:read'])]
    private ?array $availability = null;

    public function getAvailability(): ?array
    {
        return $this->availability;
    }

    public function setAvailability(?array $availability): static
    {
        $this->availability = $availability;
        return $this;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getDocumentType(): ?DocumentType
    {
        return $this->documentType;
    }

    public function setDocumentType(?DocumentType $documentType): static
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function isAutoPublished(): bool
    {
        return $this->isAutoPublished;
    }

    public function setIsAutoPublished(bool $isAutoPublished): static
    {
        $this->isAutoPublished = $isAutoPublished;
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }
}
