<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\MembershipInviteInput;
use App\Enum\InstituteRoleEnum;
use App\Enum\MembershipStatusEnum;
use App\Repository\InstituteMembershipRepository;
use App\State\InstituteMembershipProvider;
use App\State\MembershipEmailInviteProcessor;
use App\State\MembershipInviteProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InstituteMembershipRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_user_institute', columns: ['user_id', 'institute_id'])]
#[ApiResource(
    shortName: 'institute-memberships',
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            normalizationContext: ['groups' => ['membership:read'], 'skip_null_values' => false],
        ),
        new Get(
            security: "is_granted('INSTITUTE_MANAGE_MEMBERS', object.getInstitute())",
            normalizationContext: ['groups' => ['membership:read'], 'skip_null_values' => false],
        ),
        new Patch(
            security: "is_granted('INSTITUTE_MANAGE_MEMBERS', object.getInstitute())",
            denormalizationContext: ['groups' => ['membership:write']],
            normalizationContext: ['groups' => ['membership:read'], 'skip_null_values' => false],
        ),
        new Delete(
            security: "is_granted('INSTITUTE_MANAGE_MEMBERS', object.getInstitute())",
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiResource(
    uriTemplate: '/institutes/{instituteId}/memberships',
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: InstituteMembershipProvider::class,
            normalizationContext: ['groups' => ['membership:read'], 'skip_null_values' => false],
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            processor: MembershipInviteProcessor::class,
            denormalizationContext: ['groups' => ['membership:invite']],
            normalizationContext: ['groups' => ['membership:read'], 'skip_null_values' => false],
            validationContext: ['groups' => ['membership:invite']],
        ),
        new Post(
            uriTemplate: '/institutes/{instituteId}/memberships/invite',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            read: false,
            input: MembershipInviteInput::class,
            processor: MembershipEmailInviteProcessor::class,
            normalizationContext: ['groups' => ['membership:read'], 'skip_null_values' => false],
            name: 'membership_invite_by_email',
        ),
    ],
    uriVariables: [
        'instituteId' => new Link(
            fromProperty: 'memberships',
            fromClass: Institute::class,
        ),
    ],
)]
class InstituteMembership
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['membership:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(enumType: InstituteRoleEnum::class)]
    #[Groups(['membership:read', 'membership:write', 'membership:invite', 'user:read:self'])]
    #[Assert\NotBlank(groups: ['Default', 'membership:invite'])]
    private ?InstituteRoleEnum $role = null;

    #[ORM\Column(enumType: MembershipStatusEnum::class, options: ['default' => 'ACTIVE'])]
    #[Groups(['membership:read', 'membership:write', 'user:read:self'])]
    private MembershipStatusEnum $status = MembershipStatusEnum::ACTIVE;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['membership:read'])]
    private ?\DateTimeInterface $since = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['membership:read', 'membership:write', 'membership:invite'])]
    #[Assert\NotNull(groups: ['Default', 'membership:invite'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Institute::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['membership:read', 'user:read:self'])]
    #[Assert\NotNull]
    private ?Institute $institute = null;

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getRole(): ?InstituteRoleEnum
    {
        return $this->role;
    }

    public function setRole(InstituteRoleEnum $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getStatus(): MembershipStatusEnum
    {
        return $this->status;
    }

    public function setStatus(MembershipStatusEnum $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Membre actif (status ACTIVE) : seul un membre actif conserve ses droits dans l'institut.
     */
    public function isActive(): bool
    {
        return $this->status === MembershipStatusEnum::ACTIVE;
    }

    public function getSince(): ?\DateTimeInterface
    {
        return $this->since;
    }

    public function setSince(\DateTimeInterface $since): static
    {
        $this->since = $since;
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
