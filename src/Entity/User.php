<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Embeddable\Address;
use App\Enum\CivilityEnum;
use App\Enum\GenderEnum;
use App\Enum\PlatformRoleEnum;
use App\Interface\ContactableInterface;
use App\Repository\UserRepository;
use App\State\UserMePatchProcessor;
use App\State\UserMeProvider;
use App\State\UserRegistrationProcessor;
use App\State\UserSoftDeleteProcessor;
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
        new Patch(
            security: "is_granted('ROLE_PLATFORM_ADMIN')",
            denormalizationContext: ['groups' => ['user:write:admin']],
            normalizationContext: ['groups' => ['user:read:admin']],
            validationContext: ['groups' => ['user:write:admin']],
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
class User implements UserInterface, PasswordAuthenticatedUserInterface, ContactableInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['user:read:self', 'user:read:admin', 'user:read:public', 'session:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:register', 'user:write:self', 'session:read'])]
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
    #[Groups(['user:read:self', 'user:read:admin', 'user:read:public', 'user:write:self'])]
    private ?string $avatar = null;

    #[ORM\Column(enumType: CivilityEnum::class)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:register', 'user:write:self'])]
    #[Assert\NotBlank]
    private ?CivilityEnum $civility = null;

    #[ORM\Column(enumType: GenderEnum::class, nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self'])]
    private ?GenderEnum $gender = null;

    #[ORM\Column(length: 100)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:read:public', 'user:write:register', 'user:write:self', 'session:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $firstname = null;

    #[ORM\Column(length: 100)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:read:public', 'user:write:register', 'user:write:self', 'session:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $lastname = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self'])]
    #[Assert\Length(max: 20)]
    private ?string $phone = null;

    #[ORM\Column(length: 5, nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self'])]
    #[Assert\Length(max: 5)]
    private ?string $phoneCountryCode = null;

    #[ORM\Embedded(class: Address::class, columnPrefix: 'address_')]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self'])]
    private Address $address;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self'])]
    private ?\DateTimeInterface $birthday = null;

    #[ORM\ManyToOne(targetEntity: Country::class)]
    #[ORM\JoinColumn(name: 'native_country_code', referencedColumnName: 'code', nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self'])]
    private ?Country $nativeCountry = null;

    #[ORM\ManyToOne(targetEntity: Country::class)]
    #[ORM\JoinColumn(name: 'nationality_code', referencedColumnName: 'code', nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self'])]
    private ?Country $nationality = null;

    #[ORM\ManyToOne(targetEntity: Language::class)]
    #[ORM\JoinColumn(name: 'firstlanguage_code', referencedColumnName: 'code', nullable: true)]
    #[Groups(['user:read:self', 'user:read:admin', 'user:write:self'])]
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

    public function __construct()
    {
        $this->address = new Address();
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

    #[Groups(['user:read:self', 'user:read:admin'])]
    public function isVerified(): bool
    {
        return $this->isVerified;
    }

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
