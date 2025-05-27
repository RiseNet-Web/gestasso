<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use App\Repository\ClubManagerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ClubManagerRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['club_manager:read']]
        ),
        new Get(
            security: "is_granted('CLUB_VIEW', object.getClub())",
            normalizationContext: ['groups' => ['club_manager:read', 'club_manager:details']]
        ),
        new Post(
            security: "is_granted('CLUB_MANAGE_MEMBERS', object.getClub())",
            denormalizationContext: ['groups' => ['club_manager:create']],
            normalizationContext: ['groups' => ['club_manager:read']]
        ),
        new Delete(
            security: "is_granted('CLUB_MANAGE_MEMBERS', object.getClub())"
        )
    ],
    normalizationContext: ['groups' => ['club_manager:read']],
    denormalizationContext: ['groups' => ['club_manager:create']]
)]
class ClubManager
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['club_manager:read', 'club:details'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'clubManagers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['club_manager:read', 'club_manager:details'])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'clubManagers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['club_manager:read', 'club_manager:details'])]
    private ?Club $club = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['club_manager:read', 'club_manager:details'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    // Getters and Setters

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

    public function getClub(): ?Club
    {
        return $this->club;
    }

    public function setClub(?Club $club): static
    {
        $this->club = $club;
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

    // Helper methods

    /**
     * Retourne le nom complet du gestionnaire
     */
    #[Groups(['club_manager:read'])]
    public function getManagerName(): string
    {
        return $this->user?->getFullName() ?? 'Utilisateur inconnu';
    }

    /**
     * Retourne le nom du club
     */
    #[Groups(['club_manager:read'])]
    public function getClubName(): string
    {
        return $this->club?->getName() ?? 'Club inconnu';
    }

    /**
     * Calcule la durée de gestion en jours
     */
    #[Groups(['club_manager:details'])]
    public function getManagementDurationInDays(): int
    {
        $now = new \DateTime();
        return $this->createdAt->diff($now)->days;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - Gestionnaire de %s',
            $this->getManagerName(),
            $this->getClubName()
        );
    }
} 