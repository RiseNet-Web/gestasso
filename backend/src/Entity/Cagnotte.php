<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\CagnotteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CagnotteRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['cagnotte:read']]
        ),
        new Get(
            security: "is_granted('CAGNOTTE_VIEW', object)",
            normalizationContext: ['groups' => ['cagnotte:read', 'cagnotte:details']]
        ),
        new Post(
            security: "is_granted('CAGNOTTE_MANAGE', object)",
            denormalizationContext: ['groups' => ['cagnotte:create']],
            normalizationContext: ['groups' => ['cagnotte:read']]
        ),
        new Put(
            security: "is_granted('CAGNOTTE_MANAGE', object)",
            denormalizationContext: ['groups' => ['cagnotte:update']],
            normalizationContext: ['groups' => ['cagnotte:read']]
        )
    ],
    normalizationContext: ['groups' => ['cagnotte:read']],
    denormalizationContext: ['groups' => ['cagnotte:create']]
)]
class Cagnotte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cagnotte:read', 'user:details', 'team:details'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'cagnottes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['cagnotte:read', 'cagnotte:details'])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'cagnottes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['cagnotte:read', 'cagnotte:details'])]
    private ?Team $team = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero(message: 'Le montant actuel doit être positif ou nul.')]
    #[Groups(['cagnotte:read', 'cagnotte:details'])]
    private ?string $currentAmount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero(message: 'Le total gagné doit être positif ou nul.')]
    #[Groups(['cagnotte:read', 'cagnotte:details'])]
    private ?string $totalEarned = '0.00';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['cagnotte:details'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['cagnotte:details'])]
    private ?\DateTimeInterface $updatedAt = null;

    // Relations

    #[ORM\OneToMany(mappedBy: 'cagnotte', targetEntity: CagnotteTransaction::class, cascade: ['persist', 'remove'])]
    #[Groups(['cagnotte:details'])]
    private Collection $cagnotteTransactions;

    public function __construct()
    {
        $this->cagnotteTransactions = new ArrayCollection();
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

    public function getCurrentAmount(): ?string
    {
        return $this->currentAmount;
    }

    public function setCurrentAmount(string $currentAmount): static
    {
        $this->currentAmount = $currentAmount;
        return $this;
    }

    public function getTotalEarned(): ?string
    {
        return $this->totalEarned;
    }

    public function setTotalEarned(string $totalEarned): static
    {
        $this->totalEarned = $totalEarned;
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
            $cagnotteTransaction->setCagnotte($this);
        }
        return $this;
    }

    public function removeCagnotteTransaction(CagnotteTransaction $cagnotteTransaction): static
    {
        if ($this->cagnotteTransactions->removeElement($cagnotteTransaction)) {
            if ($cagnotteTransaction->getCagnotte() === $this) {
                $cagnotteTransaction->setCagnotte(null);
            }
        }
        return $this;
    }

    // Helper methods - LOGIQUE MÉTIER CRITIQUE POUR LES CAGNOTTES

    /**
     * Ajoute un montant à la cagnotte (gain d'événement)
     */
    public function addAmount(float $amount): static
    {
        $currentAmount = (float) $this->currentAmount;
        $totalEarned = (float) $this->totalEarned;
        
        $this->currentAmount = (string) ($currentAmount + $amount);
        $this->totalEarned = (string) ($totalEarned + $amount);
        $this->updatedAt = new \DateTime();
        
        return $this;
    }

    /**
     * Retire un montant de la cagnotte (utilisation pour paiement)
     */
    public function subtractAmount(float $amount): static
    {
        $currentAmount = (float) $this->currentAmount;
        
        if ($currentAmount >= $amount) {
            $this->currentAmount = (string) ($currentAmount - $amount);
            $this->updatedAt = new \DateTime();
        } else {
            throw new \InvalidArgumentException('Montant insuffisant dans la cagnotte');
        }
        
        return $this;
    }

    /**
     * Vérifie si la cagnotte a suffisamment de fonds
     */
    public function hasSufficientFunds(float $amount): bool
    {
        return (float) $this->currentAmount >= $amount;
    }

    /**
     * Retourne le montant actuel en float
     */
    #[Groups(['cagnotte:read'])]
    public function getCurrentAmountFloat(): float
    {
        return (float) $this->currentAmount;
    }

    /**
     * Retourne le total gagné en float
     */
    #[Groups(['cagnotte:read'])]
    public function getTotalEarnedFloat(): float
    {
        return (float) $this->totalEarned;
    }

    /**
     * Retourne le montant total utilisé (total gagné - montant actuel)
     */
    #[Groups(['cagnotte:read'])]
    public function getTotalUsed(): float
    {
        return $this->getTotalEarnedFloat() - $this->getCurrentAmountFloat();
    }

    /**
     * Retourne le pourcentage d'utilisation de la cagnotte
     */
    #[Groups(['cagnotte:read'])]
    public function getUsagePercentage(): float
    {
        $totalEarned = $this->getTotalEarnedFloat();
        if ($totalEarned === 0.0) {
            return 0.0;
        }
        return ($this->getTotalUsed() / $totalEarned) * 100;
    }

    /**
     * Retourne les transactions de gains (type 'earning')
     */
    public function getEarningTransactions(): Collection
    {
        return $this->cagnotteTransactions->filter(function(CagnotteTransaction $transaction) {
            return $transaction->getType() === CagnotteTransaction::TYPE_EARNING;
        });
    }

    /**
     * Retourne les transactions d'utilisation (type 'usage')
     */
    public function getUsageTransactions(): Collection
    {
        return $this->cagnotteTransactions->filter(function(CagnotteTransaction $transaction) {
            return $transaction->getType() === CagnotteTransaction::TYPE_USAGE;
        });
    }

    /**
     * Retourne le nombre d'événements auxquels l'utilisateur a participé
     */
    #[Groups(['cagnotte:read'])]
    public function getEventCount(): int
    {
        return $this->getEarningTransactions()->count();
    }

    /**
     * Retourne la dernière transaction
     */
    public function getLastTransaction(): ?CagnotteTransaction
    {
        $transactions = $this->cagnotteTransactions->toArray();
        if (empty($transactions)) {
            return null;
        }
        
        usort($transactions, function(CagnotteTransaction $a, CagnotteTransaction $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });
        
        return $transactions[0];
    }

    /**
     * Retourne les transactions triées par date (plus récentes en premier)
     */
    public function getTransactionsSortedByDate(): array
    {
        $transactions = $this->cagnotteTransactions->toArray();
        
        usort($transactions, function(CagnotteTransaction $a, CagnotteTransaction $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });
        
        return $transactions;
    }

    /**
     * Vérifie si la cagnotte est active (a des fonds ou des transactions)
     */
    #[Groups(['cagnotte:read'])]
    public function isActive(): bool
    {
        return $this->getCurrentAmountFloat() > 0 || $this->cagnotteTransactions->count() > 0;
    }

    /**
     * Retourne un résumé de la cagnotte
     */
    #[Groups(['cagnotte:read'])]
    public function getSummary(): array
    {
        return [
            'currentAmount' => $this->getCurrentAmountFloat(),
            'totalEarned' => $this->getTotalEarnedFloat(),
            'totalUsed' => $this->getTotalUsed(),
            'usagePercentage' => $this->getUsagePercentage(),
            'eventCount' => $this->getEventCount(),
            'isActive' => $this->isActive()
        ];
    }

    public function __toString(): string
    {
        return sprintf(
            'Cagnotte %s - %s (%.2f€)',
            $this->user?->getFullName() ?? 'Unknown',
            $this->team?->getName() ?? 'Unknown',
            $this->getCurrentAmountFloat()
        );
    }
} 