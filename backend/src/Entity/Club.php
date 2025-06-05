<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\ClubRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClubRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['club:read']]
        ),
        new Get(
            security: "is_granted('CLUB_VIEW', object)",
            normalizationContext: ['groups' => ['club:read', 'club:details']]
        ),
        new Post(
            security: "is_granted('ROLE_USER')",
            denormalizationContext: ['groups' => ['club:create']],
            normalizationContext: ['groups' => ['club:read']]
        ),
        new Put(
            security: "is_granted('CLUB_EDIT', object)",
            denormalizationContext: ['groups' => ['club:update']],
            normalizationContext: ['groups' => ['club:read']]
        ),
        new Delete(
            security: "is_granted('CLUB_DELETE', object)"
        )
    ],
    normalizationContext: ['groups' => ['club:read']],
    denormalizationContext: ['groups' => ['club:create']]
)]
class Club
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['club:read', 'team:read', 'user:details'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du club est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['club:read', 'club:create', 'club:update', 'team:read', 'user:details'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['club:read', 'club:create', 'club:update', 'club:details'])]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['club:read', 'club:create', 'club:update', 'club:details'])]
    private ?string $imagePath = null;

    #[ORM\ManyToOne(inversedBy: 'ownedClubs')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['club:read', 'club:details'])]
    private ?User $owner = null;

    #[ORM\Column]
    #[Groups(['club:read', 'club:create', 'club:update'])]
    private bool $isPublic = true;

    #[ORM\Column]
    #[Groups(['club:read', 'club:create', 'club:update'])]
    private bool $allowJoinRequests = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['club:details'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['club:details'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column]
    #[Groups(['club:details'])]
    private ?bool $isActive = true;

    // Relations

    #[ORM\OneToMany(mappedBy: 'club', targetEntity: Season::class, cascade: ['persist', 'remove'])]
    #[Groups(['club:details'])]
    private Collection $seasons;

    #[ORM\OneToMany(mappedBy: 'club', targetEntity: Team::class, cascade: ['persist', 'remove'])]
    #[Groups(['club:details'])]
    private Collection $teams;

    #[ORM\OneToMany(mappedBy: 'club', targetEntity: ClubManager::class, cascade: ['persist', 'remove'])]
    #[Groups(['club:details'])]
    private Collection $clubManagers;

    public function __construct()
    {
        $this->seasons = new ArrayCollection();
        $this->teams = new ArrayCollection();
        $this->clubManagers = new ArrayCollection();
    }

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
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

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function allowJoinRequests(): bool
    {
        return $this->allowJoinRequests;
    }

    public function setAllowJoinRequests(bool $allowJoinRequests): static
    {
        $this->allowJoinRequests = $allowJoinRequests;
        return $this;
    }

    // Collection methods

    /**
     * @return Collection<int, Season>
     */
    public function getSeasons(): Collection
    {
        return $this->seasons;
    }

    public function addSeason(Season $season): static
    {
        if (!$this->seasons->contains($season)) {
            $this->seasons->add($season);
            $season->setClub($this);
        }
        return $this;
    }

    public function removeSeason(Season $season): static
    {
        if ($this->seasons->removeElement($season)) {
            if ($season->getClub() === $this) {
                $season->setClub(null);
            }
        }
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
            $team->setClub($this);
        }
        return $this;
    }

    public function removeTeam(Team $team): static
    {
        if ($this->teams->removeElement($team)) {
            if ($team->getClub() === $this) {
                $team->setClub(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ClubManager>
     */
    public function getClubManagers(): Collection
    {
        return $this->clubManagers;
    }

    public function addClubManager(ClubManager $clubManager): static
    {
        if (!$this->clubManagers->contains($clubManager)) {
            $this->clubManagers->add($clubManager);
            $clubManager->setClub($this);
        }
        return $this;
    }

    public function removeClubManager(ClubManager $clubManager): static
    {
        if ($this->clubManagers->removeElement($clubManager)) {
            if ($clubManager->getClub() === $this) {
                $clubManager->setClub(null);
            }
        }
        return $this;
    }
            
    // Helper methods

    /**
     * Vérifie si un utilisateur est propriétaire du club
     */
    public function isOwner(User $user): bool
    {
        return $this->owner === $user;
    }

    /**
     * Vérifie si un utilisateur est gestionnaire du club
     */
    public function isManager(User $user): bool
    {
        foreach ($this->clubManagers as $manager) {
            if ($manager->getUser() === $user) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifie si un utilisateur a accès au club (propriétaire, gestionnaire ou membre d'une équipe)
     */
    public function hasAccess(User $user): bool
    {
        if ($this->isOwner($user) || $this->isManager($user)) {
            return true;
        }

        // Vérifier si l'utilisateur est membre d'une équipe du club
        foreach ($this->teams as $team) {
            foreach ($team->getTeamMembers() as $member) {
                if ($member->getUser() === $user && $member->isActive()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Retourne la saison active du club
     */
    public function getActiveSeason(): ?Season
    {
        foreach ($this->seasons as $season) {
            if ($season->isActive()) {
                return $season;
            }
        }
        return null;
    }

    /**
     * Retourne les équipes actives du club
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