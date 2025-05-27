<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Enum\PaymentDeductionType;
use App\Enum\PaymentDeductionCalculationType;
use App\Repository\PaymentDeductionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentDeductionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['payment_deduction:read']],
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            normalizationContext: ['groups' => ['payment_deduction:read', 'payment_deduction:details']],
            security: "is_granted('PAYMENT_DEDUCTION_VIEW', object)"
        ),
        new Post(
            denormalizationContext: ['groups' => ['payment_deduction:create']],
            normalizationContext: ['groups' => ['payment_deduction:read']],
            security: "is_granted('ROLE_CLUB_MANAGER')"
        ),
        new Put(
            denormalizationContext: ['groups' => ['payment_deduction:update']],
            normalizationContext: ['groups' => ['payment_deduction:read']],
            security: "is_granted('PAYMENT_DEDUCTION_EDIT', object)"
        ),
        new Delete(
            security: "is_granted('PAYMENT_DEDUCTION_DELETE', object)"
        )
    ]
)]
class PaymentDeduction
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['payment_deduction:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de la déduction est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['payment_deduction:read', 'payment_deduction:create', 'payment_deduction:update'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['payment_deduction:read', 'payment_deduction:details', 'payment_deduction:create', 'payment_deduction:update'])]
    private ?string $description = null;

    #[ORM\Column(length: 50, enumType: PaymentDeductionType::class)]
    #[Assert\NotNull(message: 'Le type est obligatoire')]
    #[Groups(['payment_deduction:read', 'payment_deduction:create', 'payment_deduction:update'])]
    private ?PaymentDeductionType $type = null;

    #[ORM\Column(length: 50, enumType: PaymentDeductionCalculationType::class)]
    #[Assert\NotNull(message: 'Le mode de calcul est obligatoire')]
    #[Groups(['payment_deduction:read', 'payment_deduction:create', 'payment_deduction:update'])]
    private ?PaymentDeductionCalculationType $calculationType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'La valeur est obligatoire')]
    #[Assert\Positive(message: 'La valeur doit être positive')]
    #[Groups(['payment_deduction:read', 'payment_deduction:create', 'payment_deduction:update'])]
    private ?string $value = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le montant maximum doit être positif')]
    #[Groups(['payment_deduction:read', 'payment_deduction:create', 'payment_deduction:update'])]
    private ?string $maxAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le montant minimum doit être positif')]
    #[Groups(['payment_deduction:read', 'payment_deduction:create', 'payment_deduction:update'])]
    private ?string $minAmount = null;

    #[ORM\ManyToOne(inversedBy: 'paymentDeductions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'équipe est obligatoire')]
    #[Groups(['payment_deduction:read', 'payment_deduction:details'])]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[Groups(['payment_deduction:read', 'payment_deduction:details'])]
    private ?User $createdBy = null;

    #[ORM\Column]
    #[Groups(['payment_deduction:read', 'payment_deduction:create', 'payment_deduction:update'])]
    private bool $isActive = true;

    #[ORM\Column]
    #[Groups(['payment_deduction:read', 'payment_deduction:create', 'payment_deduction:update'])]
    private bool $isAutomatic = false;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['payment_deduction:read', 'payment_deduction:create', 'payment_deduction:update'])]
    private ?\DateTimeInterface $validFrom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['payment_deduction:read', 'payment_deduction:create', 'payment_deduction:update'])]
    private ?\DateTimeInterface $validUntil = null;

    #[ORM\Column]
    #[Groups(['payment_deduction:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column]
    #[Groups(['payment_deduction:read'])]
    private ?\DateTimeInterface $updatedAt = null;

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

    public function getType(): ?PaymentDeductionType
    {
        return $this->type;
    }

    public function setType(PaymentDeductionType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getCalculationType(): ?PaymentDeductionCalculationType
    {
        return $this->calculationType;
    }

    public function setCalculationType(PaymentDeductionCalculationType $calculationType): static
    {
        $this->calculationType = $calculationType;
        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;
        return $this;
    }

    public function getMaxAmount(): ?string
    {
        return $this->maxAmount;
    }

    public function setMaxAmount(?string $maxAmount): static
    {
        $this->maxAmount = $maxAmount;
        return $this;
    }

    public function getMinAmount(): ?string
    {
        return $this->minAmount;
    }

    public function setMinAmount(?string $minAmount): static
    {
        $this->minAmount = $minAmount;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isAutomatic(): bool
    {
        return $this->isAutomatic;
    }

    public function setIsAutomatic(bool $isAutomatic): static
    {
        $this->isAutomatic = $isAutomatic;
        return $this;
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeInterface $validFrom): static
    {
        $this->validFrom = $validFrom;
        return $this;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeInterface $validUntil): static
    {
        $this->validUntil = $validUntil;
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

    // Méthodes helper

    public function getTypeLabel(): string
    {
        return $this->type?->getLabel() ?? 'Inconnu';
    }

    public function getCalculationTypeLabel(): string
    {
        return $this->calculationType?->getLabel() ?? 'Inconnu';
    }

    public function isFixedAmount(): bool
    {
        return $this->calculationType === PaymentDeductionCalculationType::FIXED;
    }

    public function isPercentage(): bool
    {
        return $this->calculationType === PaymentDeductionCalculationType::PERCENTAGE;
    }

    public function isCagnotteType(): bool
    {
        return $this->type === PaymentDeductionType::CAGNOTTE;
    }

    public function isValidAt(\DateTimeInterface $date = null): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $date = $date ?? new \DateTime();

        if ($this->validFrom && $date < $this->validFrom) {
            return false;
        }

        if ($this->validUntil && $date > $this->validUntil) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->validUntil && $this->validUntil < new \DateTime();
    }

    public function isNotYetValid(): bool
    {
        return $this->validFrom && $this->validFrom > new \DateTime();
    }

    public function calculateDeduction(float $baseAmount, ?float $availableCagnotte = null): float
    {
        if (!$this->isValidAt()) {
            return 0.0;
        }

        $deduction = 0.0;

        if ($this->isFixedAmount()) {
            $deduction = (float) $this->value;
        } elseif ($this->isPercentage()) {
            $percentage = (float) $this->value;
            $deduction = $baseAmount * ($percentage / 100);
        }

        // Pour les déductions de type cagnotte, vérifier les fonds disponibles
        if ($this->isCagnotteType() && $availableCagnotte !== null) {
            $deduction = min($deduction, $availableCagnotte);
        }

        // Appliquer les limites min/max
        if ($this->minAmount !== null) {
            $deduction = max($deduction, (float) $this->minAmount);
        }

        if ($this->maxAmount !== null) {
            $deduction = min($deduction, (float) $this->maxAmount);
        }

        // Ne pas dépasser le montant de base
        $deduction = min($deduction, $baseAmount);

        return round($deduction, 2);
    }

    public function getFormattedValue(): string
    {
        if ($this->isPercentage()) {
            return $this->value . '%';
        }
        
        return number_format((float) $this->value, 2, ',', ' ') . ' €';
    }

    public function getValidityPeriod(): string
    {
        if (!$this->validFrom && !$this->validUntil) {
            return 'Toujours valide';
        }

        $from = $this->validFrom ? $this->validFrom->format('d/m/Y') : 'Début';
        $until = $this->validUntil ? $this->validUntil->format('d/m/Y') : 'Fin';

        return "Du {$from} au {$until}";
    }

    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->validUntil) {
            return null;
        }

        $now = new \DateTime();
        $diff = $now->diff($this->validUntil);
        
        return $diff->invert ? -$diff->days : $diff->days;
    }

    public function isExpiringSoon(int $daysThreshold = 30): bool
    {
        $daysUntilExpiration = $this->getDaysUntilExpiration();
        
        return $daysUntilExpiration !== null && 
               $daysUntilExpiration >= 0 && 
               $daysUntilExpiration <= $daysThreshold;
    }

    public function canBeAppliedTo(User $user, float $baseAmount): bool
    {
        if (!$this->isValidAt()) {
            return false;
        }

        // Vérifications spécifiques selon le type
        switch ($this->type) {
            case self::TYPE_CAGNOTTE:
                // Vérifier que l'utilisateur a une cagnotte avec des fonds
                $cagnotte = $user->getCagnotteForTeam($this->team);
                return $cagnotte && $cagnotte->getCurrentAmount() > 0;

            case self::TYPE_EARLY_PAYMENT:
                // Logique pour paiement anticipé (à implémenter selon les règles métier)
                return true;

            case self::TYPE_FAMILY_DISCOUNT:
                // Vérifier si l'utilisateur a plusieurs enfants dans l'équipe/club
                return $this->team->getClub()->hasFamilyMembers($user);

            default:
                return true;
        }
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
} 