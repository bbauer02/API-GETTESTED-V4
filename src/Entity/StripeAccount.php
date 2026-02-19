<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\StripeAccountRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StripeAccountRepository::class)]
#[ApiResource(
    operations: [
        new Get(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            normalizationContext: ['groups' => ['stripe_account:read']],
        ),
        new Post(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['stripe_account:write']],
            normalizationContext: ['groups' => ['stripe_account:read']],
        ),
        new Patch(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['stripe_account:write']],
            normalizationContext: ['groups' => ['stripe_account:read']],
        ),
    ],
)]
class StripeAccount
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['stripe_account:read', 'institute:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['stripe_account:read', 'stripe_account:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $stripeId = null;

    #[ORM\Column]
    #[Groups(['stripe_account:read', 'stripe_account:write', 'institute:read'])]
    private bool $isActivated = false;

    #[ORM\OneToOne(inversedBy: 'stripeAccount', targetEntity: Institute::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['stripe_account:read', 'stripe_account:write'])]
    private ?Institute $institute = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getStripeId(): ?string
    {
        return $this->stripeId;
    }

    public function setStripeId(string $stripeId): static
    {
        $this->stripeId = $stripeId;
        return $this;
    }

    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    public function setIsActivated(bool $isActivated): static
    {
        $this->isActivated = $isActivated;
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
}
