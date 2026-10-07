<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Filter\TextSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\AdminUserInviteInput;
use App\Entity\Embeddable\Address;
use App\Enum\CivilityEnum;
use App\Enum\GenderEnum;
use App\Enum\PlatformRoleEnum;
use App\Interface\ContactableInterface;
use App\Repository\UserRepository;
use App\State\AdminUserInviteProcessor;
use App\State\UserMePatchProcessor;
use App\State\UserMeProvider;
use App\State\UserRegistrationProcessor;
use App\State\UserSoftDeleteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['deleted_at'], name: 'idx_user_deleted_at')]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse email est déjà utilisée.')]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/users/me',
            provider: UserMeProvider::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            normalizationContext: ['groups' => ['user:read:self']],
            name: 'user_me',
        ),
        new Patch(
            uriTemplate: '/users/me',
            provider: UserMeProvider::class,
            processor: UserMePatchProcessor::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            denormalizationContext: ['groups' => ['user:write:self']],
            normalizationContext: ['groups' => ['user:read:self']],
            validationContext: ['groups' => ['user:write:self']],
            name: 'user_me_patch',
        ),
        new GetCollection(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            normalizationContext: ['groups' => ['user:read:admin']],
        ),
        new Get(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            normalizationContext: ['groups' => ['user:read:admin']],
        ),
        new Post(
            uriTemplate: '/auth/register',
            denormalizationContext: ['groups' => ['user:write:register']],
            normalizationContext: ['groups' => ['user:read:self']],
            validationContext: ['groups' => ['Default', 'user:write:register']],
            processor: UserRegistrationProcessor::class,
            name: 'user_register',
        ),
        // Création d'un compte par l'admin plateforme : invitation par email (définition du mot de passe)
        new Post(
            uriTemplate: '/admin/users/invite',
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            input: AdminUserInviteInput::class,
            processor: AdminUserInviteProcessor::class,
            normalizationContext: ['groups' => ['user:read:admin']],
            read: false,
            name: 'admin_user_invite',
        ),
        new Patch(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['user:write:admin', 'user:write:self']],
            normalizationContext: ['groups' => ['user:read:admin']],
            validationContext: ['groups' => ['user:write:admin', 'user:write:self']],
        ),
        new Delete(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            processor: UserSoftDeleteProcessor::class,
        ),
    ],
    paginationItemsPerPage: 30,
)]
#[ApiFilter(SearchFilter::class, properties: [
    'email' => 'exact',
    'firstname' => 'partial',
    'lastname' => 'partial',
    'platformRole' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['isActive', 'isVerified'])]
#[ApiFilter(TextSearchFilter::class, properties: ['firstname' => null, 'lastname' => null, 'email' => null])]
#[ApiFilter(OrderFilter::class, properties: ['lastname', 'firstname', 'email', 'platformRole', 'createdAt'], arguments: ['orderParameterName' => 'order'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, ContactableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['user:read:self', 'user:read:admin', 'user:read:public', 'session:read', 'membership:read', 'scheduled_exam:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:read:public', 'user:write:register', 'user:write:self', 'session:read', 'membership:read', 'scheduled_exam:read', 'enrollment:read'])]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups(['user:write:register'])]
    #[Assert\NotBlank(groups: ['user:write:register'])]
    #[Assert\Length(min: 8, groups: ['user:write:register'])]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:read:public', 'membership:read', 'scheduled_exam:read', 'session:read', 'enrollment:read'])]
    private ?string $avatar = null;

    #[ORM\Column(enumType: CivilityEnum::class)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:register', 'user:write:self', 'session:read'])]
    #[Assert\NotBlank]
    private ?CivilityEnum $civility = null;

    #[ORM\Column(enumType: GenderEnum::class, nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self', 'session:read'])]
    private ?GenderEnum $gender = null;

    #[ORM\Column(length: 100)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:read:public', 'user:write:register', 'user:write:self', 'session:read', 'membership:read', 'scheduled_exam:read', 'enrollment:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $firstname = null;

    #[ORM\Column(length: 100)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:read:public', 'user:write:register', 'user:write:self', 'session:read', 'membership:read', 'scheduled_exam:read', 'enrollment:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $lastname = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self', 'session:read', 'enrollment:read', 'membership:read'])]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(pattern: '/^\d+$/', message: 'Le numéro de téléphone ne doit contenir que des chiffres (sans indicatif).')]
    private ?string $phone = null;

    #[ORM\Column(length: 5, nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self', 'session:read', 'enrollment:read', 'membership:read'])]
    #[Assert\Length(max: 5)]
    #[Assert\Regex(pattern: '/^\+\d{1,4}$/', message: 'L\'indicatif doit commencer par + suivi de 1 à 4 chiffres.')]
    private ?string $phoneCountryCode = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'session:read', 'enrollment:read', 'membership:read'])]
    private ?\DateTimeInterface $phoneVerifiedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phoneVerificationCode = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $phoneVerificationExpiresAt = null;

    #[ORM\Embedded(class: Address::class, columnPrefix: 'address_')]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self', 'session:read'])]
    private Address $address;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self', 'session:read'])]
    private ?\DateTimeInterface $birthday = null;

    #[ORM\ManyToOne(targetEntity: Country::class)]
    #[ORM\JoinColumn(name: 'native_country_code', referencedColumnName: 'code', nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self', 'session:read'])]
    private ?Country $nativeCountry = null;

    #[ORM\ManyToOne(targetEntity: Country::class)]
    #[ORM\JoinColumn(name: 'nationality_code', referencedColumnName: 'code', nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self', 'session:read'])]
    private ?Country $nationality = null;

    #[ORM\ManyToOne(targetEntity: Language::class)]
    #[ORM\JoinColumn(name: 'firstlanguage_code', referencedColumnName: 'code', nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self', 'session:read'])]
    private ?Language $firstlanguage = null;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin'])]
    private ?\DateTimeInterface $emailVerifiedAt = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['user:read:self', 'user:read:admin'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['user:read:self', 'user:read:admin'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(length: 10, unique: true, nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:read:public', 'session:read', 'enrollment:read'])]
    private ?string $candidateNumber = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self'])]
    #[Assert\Length(max: 50)]
    private ?string $previousRegistrationNumber = null;

    #[ORM\Column(enumType: PlatformRoleEnum::class)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:admin'])]
    private PlatformRoleEnum $platformRole = PlatformRoleEnum::USER;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['user:read:admin'])]
    private ?\DateTimeInterface $deletedAt = null;

    /** @var Collection<int, InstituteMembership> */
    #[ORM\OneToMany(targetEntity: InstituteMembership::class, mappedBy: 'user')]
    #[Groups(['user:read:self'])]
    private Collection $memberships;

    public function __construct()
    {
        $this->address = new Address();
        $this->memberships = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        if ($this->platformRole === PlatformRoleEnum::ADMIN) {
            $roles[] = 'ROLE_PLATFORM_ADMIN';
        }
        return array_unique($roles);
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getCivility(): ?CivilityEnum
    {
        return $this->civility;
    }

    public function setCivility(CivilityEnum $civility): static
    {
        $this->civility = $civility;
        return $this;
    }

    public function getGender(): ?GenderEnum
    {
        return $this->gender;
    }

    public function setGender(?GenderEnum $gender): static
    {
        $this->gender = $gender;
        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;
        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getPhoneCountryCode(): ?string
    {
        return $this->phoneCountryCode;
    }

    public function setPhoneCountryCode(?string $phoneCountryCode): static
    {
        $this->phoneCountryCode = $phoneCountryCode;
        return $this;
    }

    public function getPhoneVerifiedAt(): ?\DateTimeInterface
    {
        return $this->phoneVerifiedAt;
    }

    public function setPhoneVerifiedAt(?\DateTimeInterface $phoneVerifiedAt): static
    {
        $this->phoneVerifiedAt = $phoneVerifiedAt;
        return $this;
    }

    public function getPhoneVerificationCode(): ?string
    {
        return $this->phoneVerificationCode;
    }

    public function setPhoneVerificationCode(?string $phoneVerificationCode): static
    {
        $this->phoneVerificationCode = $phoneVerificationCode;
        return $this;
    }

    public function getPhoneVerificationExpiresAt(): ?\DateTimeInterface
    {
        return $this->phoneVerificationExpiresAt;
    }

    public function setPhoneVerificationExpiresAt(?\DateTimeInterface $phoneVerificationExpiresAt): static
    {
        $this->phoneVerificationExpiresAt = $phoneVerificationExpiresAt;
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

    public function getBirthday(): ?\DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTimeInterface $birthday): static
    {
        $this->birthday = $birthday;
        return $this;
    }

    public function getNativeCountry(): ?Country
    {
        return $this->nativeCountry;
    }

    public function setNativeCountry(?Country $nativeCountry): static
    {
        $this->nativeCountry = $nativeCountry;
        return $this;
    }

    public function getNationality(): ?Country
    {
        return $this->nationality;
    }

    public function setNationality(?Country $nationality): static
    {
        $this->nationality = $nationality;
        return $this;
    }

    public function getFirstlanguage(): ?Language
    {
        return $this->firstlanguage;
    }

    public function setFirstlanguage(?Language $firstlanguage): static
    {
        $this->firstlanguage = $firstlanguage;
        return $this;
    }

    /** Exposé sous le nom "isVerified" dans les membres d'institut (contrat front). */
    #[Groups(['membership:read'])]
    public function getIsVerified(): bool
    {
        return $this->isVerified;
    }

    /** Exposé sous le nom "isActive" dans les membres d'institut (contrat front). */
    #[Groups(['membership:read'])]
    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    #[Groups(['user:read:self', 'user:read:admin'])]
    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    #[Groups(['user:write:admin'])]
    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTimeInterface
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeInterface $emailVerifiedAt): static
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
        return $this;
    }

    #[Groups(['user:read:self', 'user:read:admin'])]
    public function isActive(): bool
    {
        return $this->isActive;
    }

    #[Groups(['user:write:admin'])]
    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getCandidateNumber(): ?string
    {
        return $this->candidateNumber;
    }

    public function setCandidateNumber(?string $candidateNumber): static
    {
        $this->candidateNumber = $candidateNumber;
        return $this;
    }

    public function getPreviousRegistrationNumber(): ?string
    {
        return $this->previousRegistrationNumber;
    }

    public function setPreviousRegistrationNumber(?string $previousRegistrationNumber): static
    {
        $this->previousRegistrationNumber = $previousRegistrationNumber;
        return $this;
    }

    public function getPlatformRole(): PlatformRoleEnum
    {
        return $this->platformRole;
    }

    public function setPlatformRole(PlatformRoleEnum $platformRole): static
    {
        $this->platformRole = $platformRole;
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /** @return Collection<int, InstituteMembership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    // ContactableInterface
    public function getName(): string
    {
        return $this->firstname . ' ' . $this->lastname;
    }

    public function getContactAddress(): ?string
    {
        return $this->address->getAddress1();
    }

    public function getContactZipcode(): ?string
    {
        return $this->address->getZipcode();
    }

    public function getContactCity(): ?string
    {
        return $this->address->getCity();
    }

    public function getContactCountry(): ?string
    {
        return $this->address->getCountryCode();
    }
}
