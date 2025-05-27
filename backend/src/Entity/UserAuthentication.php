<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Enum\AuthProvider;
use App\Repository\UserAuthenticationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserAuthenticationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['provider', 'provider_id'], name: 'idx_provider_provider_id')]
#[ORM\Index(columns: ['provider', 'email'], name: 'idx_provider_email')]
#[ORM\Index(columns: ['user_id', 'is_active'], name: 'idx_user_active')]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER') and user == object.getUser()",
            normalizationContext: ['groups' => ['user_auth:read']]
        ),
        new Get(
            security: "is_granted('ROLE_USER') and user == object.getUser()",
            normalizationContext: ['groups' => ['user_auth:read', 'user_auth:details']]
        ),
        new Post(
            security: "is_granted('ROLE_USER')",
            denormalizationContext: ['groups' => ['user_auth:create']],
            normalizationContext: ['groups' => ['user_auth:read']]
        ),
        new Put(
            security: "is_granted('ROLE_USER') and user == object.getUser()",
            denormalizationContext: ['groups' => ['user_auth:update']],
            normalizationContext: ['groups' => ['user_auth:read']]
        ),
        new Delete(
            security: "is_granted('ROLE_USER') and user == object.getUser()"
        )
    ]
)]
class UserAuthentication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user_auth:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userAuthentications')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['user_auth:read', 'user_auth:details'])]
    private ?User $user = null;

    #[ORM\Column(length: 20, enumType: AuthProvider::class)]
    #[Assert\NotNull(message: 'Le provider est obligatoire')]
    #[Groups(['user_auth:read', 'user_auth:create'])]
    private ?AuthProvider $provider = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user_auth:read', 'user_auth:create', 'user_auth:update'])]
    private ?string $providerId = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'L\'email doit être valide')]
    #[Groups(['user_auth:read', 'user_auth:create', 'user_auth:update'])]
    private ?string $email = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user_auth:create'])]
    private ?string $password = null;

    #[ORM\Column]
    #[Groups(['user_auth:read', 'user_auth:update'])]
    private bool $isVerified = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['user_auth:read', 'user_auth:details'])]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['user_auth:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['user_auth:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column]
    #[Groups(['user_auth:read', 'user_auth:update'])]
    private bool $isActive = true;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getProvider(): ?AuthProvider
    {
        return $this->provider;
    }

    public function setProvider(AuthProvider $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function getProviderId(): ?string
    {
        return $this->providerId;
    }

    public function setProviderId(?string $providerId): static
    {
        $this->providerId = $providerId;
        return $this;
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

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    // Helper methods

    public function isEmailProvider(): bool
    {
        return $this->provider === AuthProvider::EMAIL;
    }

    public function isGoogleProvider(): bool
    {
        return $this->provider === AuthProvider::GOOGLE;
    }

    public function isAppleProvider(): bool
    {
        return $this->provider === AuthProvider::APPLE;
    }

    public function isSocialProvider(): bool
    {
        return $this->provider?->isSocial() ?? false;
    }

    public function updateLastLogin(): static
    {
        $this->lastLoginAt = new \DateTime();
        return $this;
    }

    public function verify(): static
    {
        $this->isVerified = true;
        return $this;
    }

    #[Groups(['user_auth:read'])]
    public function getProviderLabel(): string
    {
        return $this->provider?->getLabel() ?? 'Inconnu';
    }

    public function __toString(): string
    {
        return sprintf(
            '%s (%s) - %s',
            $this->email,
            $this->getProviderLabel(),
            $this->isVerified ? 'Vérifié' : 'Non vérifié'
        );
    }
} 