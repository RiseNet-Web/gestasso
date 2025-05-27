<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\SeasonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SeasonRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['season:read']]
        ),
        new Get(
            security: "is_granted('CLUB_VIEW', object.getClub())",
            normalizationContext: ['groups' => ['season:read', 'season:details']]
        ),
        new Post(
            security: "is_granted('CLUB_EDIT', object.getClub())",
            denormalizationContext: ['groups' => ['season:create']],
            normalizationContext: ['groups' => ['season:read']]
        ),
        new Put(
            security: "is_granted('CLUB_EDIT', object.getClub())",
            denormalizationContext: ['groups' => ['season:update']],
            normalizationContext: ['groups' => ['season:read']]
        ),
        new Delete(
            security: "is_granted('CLUB_EDIT', object.getClub())"
        )
    ],
    normalizationContext: ['groups' => ['season:read']],
    denormalizationContext: ['groups' => ['season:create']]
)]
class Season
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['season:read', 'team:read', 'club:details'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de la saison est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['season:read', 'season:create', 'season:update', 'team:read', 'club:details'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de début est obligatoire.')]
    #[Groups(['season:read', 'season:create', 'season:update', 'season:details'])]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de fin est obligatoire.')]
    #[Assert\GreaterThan(propertyPath: 'startDate', message: 'La date de fin doit être postérieure à la date de début.')]
    #[Groups(['season:read', 'season:create', 'season:update', 'season:details'])]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\ManyToOne(inversedBy: 'seasons')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['season:read', 'season:details'])]
    private ?Club $club = null;

    #[ORM\Column]
    #[Groups(['season:read', 'season:details'])]
    private ?bool $isActive = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['season:details'])]
    private ?\DateTimeInterface $createdAt = null;

    // Relations

    #[ORM\OneToMany(mappedBy: 'season', targetEntity: Team::class)]
    #[Groups(['season:details'])]
    private Collection $teams;

    public function __construct()
    {
        $this->teams = new ArrayCollection();
    }

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
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

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    /**
     * @return Collection<int, Team>
     */
    public function getTeams(): Collection
    {
        return $this->teams;
    }

    public function addTeam(Team $team): static
    {
        if (!$this->teams->contains($team)) {
            $this->teams->add($team);
            $team->setSeason($this);
        }
        return $this;
    }

    public function removeTeam(Team $team): static
    {
        if ($this->teams->removeElement($team)) {
            if ($team->getSeason() === $this) {
                $team->setSeason(null);
            }
        }
        return $this;
    }

    // Helper methods

    /**
     * Vérifie si la saison est en cours
     */
    #[Groups(['season:read'])]
    public function isCurrent(): bool
    {
        $now = new \DateTime();
        return $this->startDate <= $now && $now <= $this->endDate;
    }

    /**
     * Vérifie si la saison est future
     */
    #[Groups(['season:read'])]
    public function isFuture(): bool
    {
        $now = new \DateTime();
        return $this->startDate > $now;
    }

    /**
     * Vérifie si la saison est passée
     */
    #[Groups(['season:read'])]
    public function isPast(): bool
    {
        $now = new \DateTime();
        return $this->endDate < $now;
    }

    /**
     * Retourne la durée de la saison en jours
     */
    #[Groups(['season:details'])]
    public function getDurationInDays(): int
    {
        return $this->startDate->diff($this->endDate)->days;
    }

    /**
     * Retourne les équipes actives de la saison
     */
    public function getActiveTeams(): Collection
    {
        return $this->teams->filter(function(Team $team) {
            return $team->isActive();
        });
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
} 