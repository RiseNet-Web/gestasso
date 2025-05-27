<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Enum\CagnotteTransactionType;
use App\Repository\CagnotteTransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CagnotteTransactionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['cagnotte_transaction:read']]
        ),
        new Get(
            security: "is_granted('CAGNOTTE_VIEW', object.getCagnotte())",
            normalizationContext: ['groups' => ['cagnotte_transaction:read', 'cagnotte_transaction:details']]
        )
    ],
    normalizationContext: ['groups' => ['cagnotte_transaction:read']]
)]
class CagnotteTransaction
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cagnotte_transaction:read', 'cagnotte:details'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'cagnotteTransactions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['cagnotte_transaction:read', 'cagnotte_transaction:details'])]
    private ?Cagnotte $cagnotte = null;

    #[ORM\ManyToOne(inversedBy: 'cagnotteTransactions')]
    #[Groups(['cagnotte_transaction:read', 'cagnotte_transaction:details'])]
    private ?Event $event = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire.')]
    #[Groups(['cagnotte_transaction:read', 'cagnotte_transaction:details'])]
    private ?string $amount = null;

    #[ORM\Column(length: 20, enumType: CagnotteTransactionType::class)]
    #[Groups(['cagnotte_transaction:read', 'cagnotte_transaction:details'])]
    private ?CagnotteTransactionType $type = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Groups(['cagnotte_transaction:read', 'cagnotte_transaction:details'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['cagnotte_transaction:read', 'cagnotte_transaction:details'])]
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

    public function getCagnotte(): ?Cagnotte
    {
        return $this->cagnotte;
    }

    public function setCagnotte(?Cagnotte $cagnotte): static
    {
        $this->cagnotte = $cagnotte;
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

    public function getType(): ?CagnotteTransactionType
    {
        return $this->type;
    }

    public function setType(CagnotteTransactionType $type): static
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
    #[Groups(['cagnotte_transaction:read'])]
    public function getAmountFloat(): float
    {
        return (float) $this->amount;
    }

    /**
     * Vérifie si c'est une transaction de gain
     */
    public function isEarning(): bool
    {
        return $this->type === CagnotteTransactionType::EARNING;
    }

    /**
     * Vérifie si c'est une transaction d'utilisation
     */
    public function isUsage(): bool
    {
        return $this->type === CagnotteTransactionType::USAGE;
    }

    /**
     * Vérifie si c'est une transaction d'ajustement
     */
    public function isAdjustment(): bool
    {
        return $this->type === CagnotteTransactionType::ADJUSTMENT;
    }

    /**
     * Retourne le libellé du type
     */
    #[Groups(['cagnotte_transaction:read'])]
    public function getTypeLabel(): string
    {
        return $this->type?->getLabel() ?? 'Inconnu';
    }

    /**
     * Retourne une description formatée avec l'événement si présent
     */
    #[Groups(['cagnotte_transaction:read'])]
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
    #[Groups(['cagnotte_transaction:read'])]
    public function getSignedAmount(): float
    {
        $amount = $this->getAmountFloat();
        return match($this->type) {
            CagnotteTransactionType::EARNING => $amount,
            CagnotteTransactionType::USAGE => -$amount,
            CagnotteTransactionType::ADJUSTMENT => $amount, // Peut être positif ou négatif selon le contexte
            default => $amount
        };
    }

    public function __toString(): string
    {
        return sprintf(
            '%s: %.2f€ (%s)',
            $this->getTypeLabel(),
            $this->getAmountFloat(),
            $this->description
        );
    }
} 