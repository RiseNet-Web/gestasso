<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Enum\ClubTransactionType;
use App\Repository\ClubTransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClubTransactionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_CLUB_MANAGER')",
            normalizationContext: ['groups' => ['club_transaction:read']]
        ),
        new Get(
            security: "is_granted('CLUB_VIEW', object.getClub())",
            normalizationContext: ['groups' => ['club_transaction:read', 'club_transaction:details']]
        )
    ],
    normalizationContext: ['groups' => ['club_transaction:read']]
)]
class ClubTransaction
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['club_transaction:read', 'club_finance:details'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'clubTransactions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['club_transaction:read', 'club_transaction:details'])]
    private ?Club $club = null;

    #[ORM\ManyToOne(inversedBy: 'clubTransactions')]
    #[Groups(['club_transaction:read', 'club_transaction:details'])]
    private ?Event $event = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire.')]
    #[Groups(['club_transaction:read', 'club_transaction:details'])]
    private ?string $amount = null;

    #[ORM\Column(length: 20, enumType: ClubTransactionType::class)]
    #[Groups(['club_transaction:read', 'club_transaction:details'])]
    private ?ClubTransactionType $type = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Groups(['club_transaction:read', 'club_transaction:details'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['club_transaction:read', 'club_transaction:details'])]
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

    public function getClub(): ?Club
    {
        return $this->club;
    }

    public function setClub(?Club $club): static
    {
        $this->club = $club;
        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        $this->event = $event;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getType(): ?ClubTransactionType
    {
        return $this->type;
    }

    public function setType(ClubTransactionType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
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
     * Retourne le montant en float
     */
    #[Groups(['club_transaction:read'])]
    public function getAmountFloat(): float
    {
        return (float) $this->amount;
    }

    /**
     * Vérifie si c'est une transaction de commission
     */
    public function isCommission(): bool
    {
        return $this->type === ClubTransactionType::COMMISSION;
    }

    /**
     * Vérifie si c'est une transaction de dépense
     */
    public function isExpense(): bool
    {
        return $this->type === ClubTransactionType::EXPENSE;
    }

    /**
     * Vérifie si c'est une transaction d'ajustement
     */
    public function isAdjustment(): bool
    {
        return $this->type === ClubTransactionType::ADJUSTMENT;
    }

    /**
     * Retourne le libellé du type
     */
    #[Groups(['club_transaction:read'])]
    public function getTypeLabel(): string
    {
        return $this->type?->getLabel() ?? 'Inconnu';
    }

    /**
     * Retourne une description formatée avec l'événement si présent
     */
    #[Groups(['club_transaction:read'])]
    public function getFormattedDescription(): string
    {
        if ($this->event) {
            return sprintf('%s (Événement: %s)', $this->description, $this->event->getTitle());
        }
        return $this->description;
    }

    /**
     * Vérifie si la transaction est liée à un événement
     */
    public function hasEvent(): bool
    {
        return $this->event !== null;
    }

    /**
     * Retourne le signe du montant selon le type
     */
    #[Groups(['club_transaction:read'])]
    public function getSignedAmount(): float
    {
        $amount = $this->getAmountFloat();
        return match($this->type) {
            ClubTransactionType::COMMISSION => $amount,
            ClubTransactionType::EXPENSE => -$amount,
            ClubTransactionType::ADJUSTMENT => $amount, // Peut être positif ou négatif selon le contexte
            default => $amount
        };
    }

    /**
     * Retourne le nom du club
     */
    #[Groups(['club_transaction:read'])]
    public function getClubName(): string
    {
        return $this->club?->getName() ?? 'Club inconnu';
    }

    public function __toString(): string
    {
        return sprintf(
            '%s: %.2f€ (%s) - %s',
            $this->getTypeLabel(),
            $this->getAmountFloat(),
            $this->getClubName(),
            $this->description
        );
    }
} 