<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Enum\EventStatus;
use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['event:read']]
        ),
        new Get(
            security: "is_granted('EVENT_VIEW', object)",
            normalizationContext: ['groups' => ['event:read', 'event:details']]
        ),
        new Post(
            security: "is_granted('EVENT_CREATE', object)",
            denormalizationContext: ['groups' => ['event:create']],
            normalizationContext: ['groups' => ['event:read']]
        ),
        new Put(
            security: "is_granted('EVENT_EDIT', object)",
            denormalizationContext: ['groups' => ['event:update']],
            normalizationContext: ['groups' => ['event:read']]
        ),
        new Delete(
            security: "is_granted('EVENT_EDIT', object)"
        )
    ],
    normalizationContext: ['groups' => ['event:read']],
    denormalizationContext: ['groups' => ['event:create']]
)]
class Event
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['event:read', 'team:details', 'cagnotte:read', 'club:details'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre de l\'événement est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['event:read', 'event:create', 'event:update', 'team:details', 'cagnotte:read'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['event:read', 'event:create', 'event:update', 'event:details'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le budget total est obligatoire.')]
    #[Assert\Positive(message: 'Le budget total doit être positif.')]
    #[Groups(['event:read', 'event:create', 'event:update', 'event:details'])]
    private ?string $totalBudget = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Assert\NotBlank(message: 'Le pourcentage du club est obligatoire.')]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'Le pourcentage doit être entre {{ min }} et {{ max }}.')]
    #[Groups(['event:read', 'event:create', 'event:update', 'event:details'])]
    private ?string $clubPercentage = null;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['event:read', 'event:details'])]
    private ?Team $team = null;

    #[ORM\ManyToOne(inversedBy: 'createdEvents')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['event:read', 'event:details'])]
    private ?User $createdBy = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['event:read', 'event:create', 'event:update', 'event:details'])]
    private ?string $imagePath = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de l\'événement est obligatoire.')]
    #[Groups(['event:read', 'event:create', 'event:update', 'event:details'])]
    private ?\DateTimeInterface $eventDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['event:details'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 20, enumType: EventStatus::class)]
    #[Groups(['event:read', 'event:details'])]
    private EventStatus $status = EventStatus::DRAFT;

    // Relations

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: EventParticipant::class, cascade: ['persist', 'remove'])]
    #[Groups(['event:details'])]
    private Collection $eventParticipants;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: CagnotteTransaction::class)]
    #[Groups(['event:details'])]
    private Collection $cagnotteTransactions;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: ClubTransaction::class)]
    private Collection $clubTransactions;

    public function __construct()
    {
        $this->eventParticipants = new ArrayCollection();
        $this->cagnotteTransactions = new ArrayCollection();
        $this->clubTransactions = new ArrayCollection();
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
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

    public function getTotalBudget(): ?string
    {
        return $this->totalBudget;
    }

    public function setTotalBudget(string $totalBudget): static
    {
        $this->totalBudget = $totalBudget;
        return $this;
    }

    public function getClubPercentage(): ?string
    {
        return $this->clubPercentage;
    }

    public function setClubPercentage(string $clubPercentage): static
    {
        $this->clubPercentage = $clubPercentage;
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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
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

    public function getEventDate(): ?\DateTimeInterface
    {
        return $this->eventDate;
    }

    public function setEventDate(\DateTimeInterface $eventDate): static
    {
        $this->eventDate = $eventDate;
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

    public function getStatus(): EventStatus
    {
        return $this->status;
    }

    public function setStatus(EventStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    // Collection methods

    /**
     * @return Collection<int, EventParticipant>
     */
    public function getEventParticipants(): Collection
    {
        return $this->eventParticipants;
    }

    public function addEventParticipant(EventParticipant $eventParticipant): static
    {
        if (!$this->eventParticipants->contains($eventParticipant)) {
            $this->eventParticipants->add($eventParticipant);
            $eventParticipant->setEvent($this);
        }
        return $this;
    }

    public function removeEventParticipant(EventParticipant $eventParticipant): static
    {
        if ($this->eventParticipants->removeElement($eventParticipant)) {
            if ($eventParticipant->getEvent() === $this) {
                $eventParticipant->setEvent(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, CagnotteTransaction>
     */
    public function getCagnotteTransactions(): Collection
    {
        return $this->cagnotteTransactions;
    }

    public function addCagnotteTransaction(CagnotteTransaction $cagnotteTransaction): static
    {
        if (!$this->cagnotteTransactions->contains($cagnotteTransaction)) {
            $this->cagnotteTransactions->add($cagnotteTransaction);
            $cagnotteTransaction->setEvent($this);
        }
        return $this;
    }

    public function removeCagnotteTransaction(CagnotteTransaction $cagnotteTransaction): static
    {
        if ($this->cagnotteTransactions->removeElement($cagnotteTransaction)) {
            if ($cagnotteTransaction->getEvent() === $this) {
                $cagnotteTransaction->setEvent(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ClubTransaction>
     */
    public function getClubTransactions(): Collection
    {
        return $this->clubTransactions;
    }

    public function addClubTransaction(ClubTransaction $clubTransaction): static
    {
        if (!$this->clubTransactions->contains($clubTransaction)) {
            $this->clubTransactions->add($clubTransaction);
            $clubTransaction->setEvent($this);
        }
        return $this;
    }

    public function removeClubTransaction(ClubTransaction $clubTransaction): static
    {
        if ($this->clubTransactions->removeElement($clubTransaction)) {
            if ($clubTransaction->getEvent() === $this) {
                $clubTransaction->setEvent(null);
            }
        }
        return $this;
    }

    // Helper methods - LOGIQUE MÉTIER CRITIQUE POUR LES CAGNOTTES

    /**
     * Calcule le montant de commission du club
     */
    #[Groups(['event:read'])]
    public function getClubCommission(): float
    {
        return (float) $this->totalBudget * ((float) $this->clubPercentage / 100);
    }

    /**
     * Calcule le montant disponible pour les participants (après commission club)
     */
    #[Groups(['event:read'])]
    public function getAvailableAmount(): float
    {
        return (float) $this->totalBudget - $this->getClubCommission();
    }

    /**
     * Calcule le montant par participant (partage équitable)
     */
    #[Groups(['event:read'])]
    public function getAmountPerParticipant(): float
    {
        $participantCount = $this->eventParticipants->count();
        if ($participantCount === 0) {
            return 0;
        }
        return $this->getAvailableAmount() / $participantCount;
    }

    /**
     * Retourne le nombre de participants
     */
    #[Groups(['event:read'])]
    public function getParticipantCount(): int
    {
        return $this->eventParticipants->count();
    }

    /**
     * Vérifie si l'événement peut être distribué (status active et participants présents)
     */
    public function canBeDistributed(): bool
    {
        return $this->status === EventStatus::ACTIVE && $this->eventParticipants->count() > 0;
    }

    /**
     * Vérifie si l'événement a déjà été distribué
     */
    public function isDistributed(): bool
    {
        return $this->cagnotteTransactions->count() > 0;
    }

    /**
     * Vérifie si un utilisateur participe à l'événement
     */
    public function hasParticipant(User $user): bool
    {
        foreach ($this->eventParticipants as $participant) {
            if ($participant->getUser() === $user) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retourne le participant pour un utilisateur donné
     */
    public function getParticipantForUser(User $user): ?EventParticipant
    {
        foreach ($this->eventParticipants as $participant) {
            if ($participant->getUser() === $user) {
                return $participant;
            }
        }
        return null;
    }

    /**
     * Vérifie si l'événement est en cours (date passée mais pas encore complété)
     */
    #[Groups(['event:read'])]
    public function isOngoing(): bool
    {
        $now = new \DateTime();
        return $this->eventDate <= $now && $this->status === EventStatus::ACTIVE;
    }

    /**
     * Vérifie si l'événement est futur
     */
    #[Groups(['event:read'])]
    public function isFuture(): bool
    {
        $now = new \DateTime();
        return $this->eventDate > $now;
    }

    /**
     * Retourne le libellé du statut
     */
    #[Groups(['event:read'])]
    public function getStatusLabel(): string
    {
        return $this->status->getLabel();
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }
} 