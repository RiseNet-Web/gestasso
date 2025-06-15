<?php

namespace App\Entity;



use App\Enum\TeamMemberRole;
use App\Repository\TeamMemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TeamMemberRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TeamMember
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['team_member:read', 'team_member:details'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'teamMemberships')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['team_member:read', 'team_member:details'])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'teamMembers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['team_member:read', 'team_member:details'])]
    private ?Team $team = null;

    #[ORM\Column(length: 50, enumType: TeamMemberRole::class)]
    #[Groups(['team_member:read', 'team_member:details'])]
    private TeamMemberRole $role = TeamMemberRole::ATHLETE;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['team_member:read', 'team_member:details'])]
    private ?\DateTimeInterface $joinedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['team_member:details'])]
    private ?\DateTimeInterface $leftAt = null;

    #[ORM\Column]
    #[Groups(['team_member:read', 'team_member:details'])]
    private ?bool $isActive = true;

    #[ORM\PrePersist]
    public function setJoinedAtValue(): void
    {
        $this->joinedAt = new \DateTime();
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

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;
        return $this;
    }

    public function getRole(): TeamMemberRole
    {
        return $this->role;
    }

    public function setRole(TeamMemberRole $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getJoinedAt(): ?\DateTimeInterface
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(\DateTimeInterface $joinedAt): static
    {
        $this->joinedAt = $joinedAt;
        return $this;
    }

    public function getLeftAt(): ?\DateTimeInterface
    {
        return $this->leftAt;
    }

    public function setLeftAt(?\DateTimeInterface $leftAt): static
    {
        $this->leftAt = $leftAt;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        if (!$isActive && !$this->leftAt) {
            $this->leftAt = new \DateTime();
        }
        return $this;
    }

    // Helper methods

    /**
     * Vérifie si le membre est un athlète
     */
    public function isAthlete(): bool
    {
        return $this->role === TeamMemberRole::ATHLETE;
    }

    /**
     * Vérifie si le membre est un coach
     */
    public function isCoach(): bool
    {
        return $this->role === TeamMemberRole::COACH;
    }

    /**
     * Retourne le libellé du rôle
     */
    #[Groups(['team_member:read'])]
    public function getRoleLabel(): string
    {
        return $this->role->getLabel();
    }

    /**
     * Retourne le nom complet du membre
     */
    #[Groups(['team_member:read'])]
    public function getMemberName(): string
    {
        return $this->user?->getFullName() ?? 'Utilisateur inconnu';
    }

    /**
     * Retourne le nom de l'équipe
     */
    #[Groups(['team_member:read'])]
    public function getTeamName(): string
    {
        return $this->team?->getName() ?? 'Équipe inconnue';
    }

    /**
     * Calcule la durée d'appartenance en jours
     */
    #[Groups(['team_member:details'])]
    public function getMembershipDurationInDays(): int
    {
        $endDate = $this->leftAt ?? new \DateTime();
        return $this->joinedAt->diff($endDate)->days;
    }

    /**
     * Vérifie si le membre est encore actif
     */
    public function isCurrentMember(): bool
    {
        return $this->isActive && $this->leftAt === null;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s (%s)',
            $this->getMemberName(),
            $this->getTeamName(),
            $this->getRoleLabel()
        );
    }
} 