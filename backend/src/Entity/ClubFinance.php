<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Put;
use App\Repository\ClubFinanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClubFinanceRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_CLUB_MANAGER')",
            normalizationContext: ['groups' => ['club_finance:read']]
        ),
        new Get(
            security: "is_granted('CLUB_VIEW', object.getClub())",
            normalizationContext: ['groups' => ['club_finance:read', 'club_finance:details']]
        ),
        new Put(
            security: "is_granted('CLUB_EDIT', object.getClub())",
            denormalizationContext: ['groups' => ['club_finance:update']],
            normalizationContext: ['groups' => ['club_finance:read']]
        )
    ],
    normalizationContext: ['groups' => ['club_finance:read']],
    denormalizationContext: ['groups' => ['club_finance:update']]
)]
class ClubFinance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['club_finance:read', 'club:details'])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'clubFinance', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['club_finance:read', 'club_finance:details'])]
    private ?Club $club = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotNull(message: 'La commission totale ne peut pas être nulle.')]
    #[Assert\PositiveOrZero(message: 'La commission totale doit être positive ou nulle.')]
    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Le format de la commission totale est invalide.')]
    #[Groups(['club_finance:read', 'club_finance:details', 'club_finance:update'])]
    private string $totalCommission = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotNull(message: 'Le solde actuel ne peut pas être nul.')]
    #[Assert\Regex(pattern: '/^-?\d+(\.\d{1,2})?$/', message: 'Le format du solde actuel est invalide.')]
    #[Groups(['club_finance:read', 'club_finance:details', 'club_finance:update'])]
    private string $currentBalance = '0.00';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['club_finance:details'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['club_finance:details'])]
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

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClub(): ?Club
    {
        return $this->club;
    }

    public function setClub(Club $club): static
    {
        $this->club = $club;
        return $this;
    }

    public function getTotalCommission(): string
    {
        return $this->totalCommission;
    }

    public function setTotalCommission(string $totalCommission): static
    {
        if (!$this->isValidAmount($totalCommission) || bccomp($totalCommission, '0', 2) < 0) {
            throw new \InvalidArgumentException('La commission totale doit être un montant positif valide.');
        }
        $this->totalCommission = bcadd($totalCommission, '0', 2); // Normalise à 2 décimales
        return $this;
    }

    public function getCurrentBalance(): string
    {
        return $this->currentBalance;
    }

    public function setCurrentBalance(string $currentBalance): static
    {
        if (!$this->isValidAmount($currentBalance, true)) {
            throw new \InvalidArgumentException('Le solde actuel doit être un montant valide.');
        }
        $this->currentBalance = bcadd($currentBalance, '0', 2); // Normalise à 2 décimales
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

    // Helper methods - LOGIQUE MÉTIER CRITIQUE POUR LES FINANCES DU CLUB

    /**
     * Ajoute une commission au club
     */
    public function addCommission(string $amount): static
    {
        if (!$this->isValidAmount($amount) || bccomp($amount, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Le montant de la commission doit être positif et valide.');
        }
        
        $this->totalCommission = bcadd($this->totalCommission, $amount, 2);
        $this->currentBalance = bcadd($this->currentBalance, $amount, 2);
        $this->updatedAt = new \DateTime();
        
        return $this;
    }

    /**
     * Retire un montant du solde (dépense du club)
     */
    public function subtractAmount(string $amount): static
    {
        if (!$this->isValidAmount($amount) || bccomp($amount, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Le montant à retirer doit être positif et valide.');
        }
        
        if (!$this->hasSufficientFunds($amount)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Solde insuffisant dans les finances du club. Solde actuel: %s€, Montant demandé: %s€',
                    $this->currentBalance,
                    $amount
                )
            );
        }
        
        $this->currentBalance = bcsub($this->currentBalance, $amount, 2);
        $this->updatedAt = new \DateTime();
        
        return $this;
    }

    /**
     * Vérifie si le club a suffisamment de fonds
     */
    public function hasSufficientFunds(string $amount): bool
    {
        if (!$this->isValidAmount($amount)) {
            return false;
        }
        return bccomp($this->currentBalance, $amount, 2) >= 0;
    }

    /**
     * Retourne la commission totale en float
     */
    #[Groups(['club_finance:read'])]
    public function getTotalCommissionFloat(): float
    {
        return (float) $this->totalCommission;
    }

    /**
     * Retourne le solde actuel en float
     */
    #[Groups(['club_finance:read'])]
    public function getCurrentBalanceFloat(): float
    {
        return (float) $this->currentBalance;
    }

    /**
     * Retourne le montant total dépensé (commission totale - solde actuel)
     */
    #[Groups(['club_finance:read'])]
    public function getTotalSpent(): string
    {
        return bcsub($this->totalCommission, $this->currentBalance, 2);
    }

    /**
     * Retourne le montant total dépensé en float
     */
    #[Groups(['club_finance:read'])]
    public function getTotalSpentFloat(): float
    {
        return (float) $this->getTotalSpent();
    }

    /**
     * Retourne le pourcentage de fonds utilisés
     */
    #[Groups(['club_finance:read'])]
    public function getUsagePercentage(): float
    {
        if (bccomp($this->totalCommission, '0', 2) === 0) {
            return 0.0;
        }
        
        $percentage = bcdiv(
            bcmul($this->getTotalSpent(), '100', 2),
            $this->totalCommission,
            4
        );
        
        return (float) $percentage;
    }

    /**
     * Vérifie si le club a des finances actives
     */
    #[Groups(['club_finance:read'])]
    public function isActive(): bool
    {
        return bccomp($this->totalCommission, '0', 2) > 0;
    }

    /**
     * Vérifie si le solde est positif
     */
    #[Groups(['club_finance:read'])]
    public function hasPositiveBalance(): bool
    {
        return bccomp($this->currentBalance, '0', 2) > 0;
    }

    /**
     * Vérifie si le solde est négatif
     */
    #[Groups(['club_finance:read'])]
    public function hasNegativeBalance(): bool
    {
        return bccomp($this->currentBalance, '0', 2) < 0;
    }

    /**
     * Retourne un résumé des finances
     */
    #[Groups(['club_finance:read'])]
    public function getFinancialSummary(): array
    {
        return [
            'totalCommission' => $this->getTotalCommissionFloat(),
            'currentBalance' => $this->getCurrentBalanceFloat(),
            'totalSpent' => $this->getTotalSpentFloat(),
            'usagePercentage' => $this->getUsagePercentage(),
            'isActive' => $this->isActive(),
            'hasPositiveBalance' => $this->hasPositiveBalance(),
            'hasNegativeBalance' => $this->hasNegativeBalance()
        ];
    }

    /**
     * Valide qu'un montant est dans le bon format
     */
    private function isValidAmount(string $amount, bool $allowNegative = false): bool
    {
        if (empty($amount)) {
            return false;
        }
        
        $pattern = $allowNegative ? '/^-?\d+(\.\d{1,2})?$/' : '/^\d+(\.\d{1,2})?$/';
        
        if (!preg_match($pattern, $amount)) {
            return false;
        }
        
        // Vérifier que le montant n'est pas trop grand
        if (bccomp(abs((float) $amount), '999999999.99', 2) > 0) {
            return false;
        }
        
        return true;
    }

    /**
     * Remet à zéro les finances du club
     */
    public function reset(): static
    {
        $this->totalCommission = '0.00';
        $this->currentBalance = '0.00';
        $this->updatedAt = new \DateTime();
        
        return $this;
    }

    /**
     * Ajuste le solde (peut être positif ou négatif)
     */
    public function adjustBalance(string $amount, string $reason = ''): static
    {
        if (!$this->isValidAmount($amount, true)) {
            throw new \InvalidArgumentException('Le montant d\'ajustement doit être valide.');
        }
        
        $this->currentBalance = bcadd($this->currentBalance, $amount, 2);
        $this->updatedAt = new \DateTime();
        
        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            'Finances %s - Solde: %s€ (Commission totale: %s€)',
            $this->club?->getName() ?? 'Club inconnu',
            $this->currentBalance,
            $this->totalCommission
        );
    }
}
 