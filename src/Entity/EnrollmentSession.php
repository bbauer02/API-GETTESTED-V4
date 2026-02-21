<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\EnrollmentSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EnrollmentSessionRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            normalizationContext: ['groups' => ['enrollment:read']],
        ),
        new Get(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            normalizationContext: ['groups' => ['enrollment:read']],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            denormalizationContext: ['groups' => ['enrollment:write']],
            normalizationContext: ['groups' => ['enrollment:read']],
        ),
        new Patch(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['enrollment:write']],
            normalizationContext: ['groups' => ['enrollment:read']],
        ),
        new Delete(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
        ),
    ],
    paginationItemsPerPage: 30,
)]
class EnrollmentSession
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['enrollment:read', 'session:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['enrollment:read', 'enrollment:write', 'session:read'])]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $registrationDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['enrollment:read', 'enrollment:write', 'session:read'])]
    private ?string $information = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['enrollment:read', 'enrollment:write', 'session:read'])]
    #[Assert\NotNull]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'enrollments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['enrollment:read', 'enrollment:write'])]
    #[Assert\NotNull]
    private ?Session $session = null;

    public function getId(): ?Uuid
    {
        return $this->id;
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
}
