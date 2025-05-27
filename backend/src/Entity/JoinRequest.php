<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Enum\JoinRequestStatus;
use App\Enum\TeamMemberRole;
use App\Repository\JoinRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JoinRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['user_id', 'status'], name: 'idx_user_status')]
#[ORM\Index(columns: ['team_id', 'status'], name: 'idx_team_status')]
#[ORM\Index(columns: ['club_id', 'status'], name: 'idx_club_status')]
#[ORM\Index(columns: ['reviewed_by_id', 'reviewed_at'], name: 'idx_reviewed_by_at')]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['join_request:read']]
        ),
        new Get(
            security: "is_granted('JOIN_REQUEST_VIEW', object)",
            normalizationContext: ['groups' => ['join_request:read', 'join_request:details']]
        ),
        new Post(
            security: "is_granted('ROLE_USER')",
            denormalizationContext: ['groups' => ['join_request:create']],
            normalizationContext: ['groups' => ['join_request:read']]
        ),
        new Put(
            security: "is_granted('JOIN_REQUEST_REVIEW', object)",
            denormalizationContext: ['groups' => ['join_request:review']],
            normalizationContext: ['groups' => ['join_request:read']]
        ),
        new Delete(
            security: "is_granted('JOIN_REQUEST_DELETE', object)"
        )
    ]
)]
class JoinRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['join_request:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'joinRequests')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'utilisateur est obligatoire')]
    #[Groups(['join_request:read', 'join_request:details'])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'joinRequests')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'équipe est obligatoire')]
    #[Groups(['join_request:read', 'join_request:details', 'join_request:create'])]
    private ?Team $team = null;

    #[ORM\ManyToOne(inversedBy: 'joinRequests')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le club est obligatoire')]
    #[Groups(['join_request:read', 'join_request:details'])]
    private ?Club $club = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['join_request:read', 'join_request:details', 'join_request:create'])]
    private ?string $message = null;

    #[ORM\Column(length: 20, enumType: JoinRequestStatus::class)]
    #[Groups(['join_request:read', 'join_request:review'])]
    private JoinRequestStatus $status = JoinRequestStatus::PENDING;

    #[ORM\Column(length: 20, enumType: TeamMemberRole::class, nullable: true)]
    #[Groups(['join_request:read', 'join_request:create'])]
    private ?TeamMemberRole $requestedRole = null;

    #[ORM\Column(length: 20, enumType: TeamMemberRole::class, nullable: true)]
    #[Groups(['join_request:read', 'join_request:review'])]
    private ?TeamMemberRole $assignedRole = null;

    #[ORM\ManyToOne(inversedBy: 'reviewedJoinRequests')]
    #[Groups(['join_request:read', 'join_request:details'])]
    private ?User $reviewedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['join_request:read', 'join_request:details'])]
    private ?\DateTimeInterface $reviewedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['join_request:read', 'join_request:details', 'join_request:review'])]
    private ?string $reviewNotes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['join_request:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['join_request:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        
        // Auto-set club from team
        if ($this->team && !$this->club) {
            $this->club = $this->team->getClub();
        }
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

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;
        if ($team) {
            $this->club = $team->getClub();
        }
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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getStatus(): JoinRequestStatus
    {
        return $this->status;
    }

    public function setStatus(JoinRequestStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getRequestedRole(): ?TeamMemberRole
    {
        return $this->requestedRole;
    }

    public function setRequestedRole(?TeamMemberRole $requestedRole): static
    {
        $this->requestedRole = $requestedRole;
        return $this;
    }

    public function getAssignedRole(): ?TeamMemberRole
    {
        return $this->assignedRole;
    }

    public function setAssignedRole(?TeamMemberRole $assignedRole): static
    {
        $this->assignedRole = $assignedRole;
        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): static
    {
        $this->reviewedBy = $reviewedBy;
        return $this;
    }

    public function getReviewedAt(): ?\DateTimeInterface
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeInterface $reviewedAt): static
    {
        $this->reviewedAt = $reviewedAt;
        return $this;
    }

    public function getReviewNotes(): ?string
    {
        return $this->reviewNotes;
    }

    public function setReviewNotes(?string $reviewNotes): static
    {
        $this->reviewNotes = $reviewNotes;
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

    // Helper methods

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isApproved(): bool
    {
        return $this->status->isApproved();
    }

    public function isRejected(): bool
    {
        return $this->status->isRejected();
    }

    public function isReviewed(): bool
    {
        return $this->status->isReviewed();
    }

    #[Groups(['join_request:read'])]
    public function getStatusLabel(): string
    {
        return $this->status->getLabel();
    }

    #[Groups(['join_request:read'])]
    public function getRequestedRoleLabel(): ?string
    {
        return $this->requestedRole?->getLabel();
    }

    #[Groups(['join_request:read'])]
    public function getAssignedRoleLabel(): ?string
    {
        return $this->assignedRole?->getLabel();
    }

    #[Groups(['join_request:read'])]
    public function getUserName(): string
    {
        return $this->user?->getFullName() ?? 'Utilisateur inconnu';
    }

    #[Groups(['join_request:read'])]
    public function getTeamName(): string
    {
        return $this->team?->getName() ?? 'Équipe inconnue';
    }

    #[Groups(['join_request:read'])]
    public function getClubName(): string
    {
        return $this->club?->getName() ?? 'Club inconnu';
    }

    public function approve(User $reviewer, ?TeamMemberRole $assignedRole = null, ?string $notes = null): static
    {
        $this->status = JoinRequestStatus::APPROVED;
        $this->reviewedBy = $reviewer;
        $this->reviewedAt = new \DateTime();
        $this->reviewNotes = $notes;
        
        if ($assignedRole) {
            $this->assignedRole = $assignedRole;
        } elseif ($this->requestedRole) {
            $this->assignedRole = $this->requestedRole;
        } else {
            $this->assignedRole = TeamMemberRole::ATHLETE; // Default role
        }
        
        return $this;
    }

    public function reject(User $reviewer, string $notes): static
    {
        $this->status = JoinRequestStatus::REJECTED;
        $this->reviewedBy = $reviewer;
        $this->reviewedAt = new \DateTime();
        $this->reviewNotes = $notes;
        $this->assignedRole = null;
        
        return $this;
    }

    public function getDaysOld(): int
    {
        $now = new \DateTime();
        return $this->createdAt->diff($now)->days;
    }

    public function isOld(int $daysThreshold = 7): bool
    {
        return $this->getDaysOld() >= $daysThreshold;
    }

    public function __toString(): string
    {
        return sprintf(
            'Demande de %s pour %s (%s)',
            $this->getUserName(),
            $this->getTeamName(),
            $this->getStatusLabel()
        );
    }
} 