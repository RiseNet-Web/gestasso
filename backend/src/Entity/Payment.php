<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Enum\PaymentStatus;
use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['payment:read']]
        ),
        new Get(
            security: "is_granted('PAYMENT_VIEW', object)",
            normalizationContext: ['groups' => ['payment:read', 'payment:details']]
        ),
        new Post(
            security: "is_granted('PAYMENT_CREATE', object)",
            denormalizationContext: ['groups' => ['payment:create']],
            normalizationContext: ['groups' => ['payment:read']]
        ),
        new Put(
            security: "is_granted('PAYMENT_UPDATE', object)",
            denormalizationContext: ['groups' => ['payment:update']],
            normalizationContext: ['groups' => ['payment:read']]
        )
    ],
    normalizationContext: ['groups' => ['payment:read']],
    denormalizationContext: ['groups' => ['payment:create']]
)]
class Payment
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['payment:read', 'user:details', 'team:details'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payment:read', 'payment:details'])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payment:read', 'payment:details'])]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[Groups(['payment:read', 'payment:details'])]
    private ?PaymentSchedule $paymentSchedule = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\Positive(message: 'Le montant doit être positif.')]
    #[Groups(['payment:read', 'payment:create', 'payment:update', 'payment:details'])]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero(message: 'Le montant payé doit être positif ou nul.')]
    #[Groups(['payment:read', 'payment:update', 'payment:details'])]
    private ?string $amountPaid = '0.00';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date d\'échéance est obligatoire.')]
    #[Groups(['payment:read', 'payment:create', 'payment:update', 'payment:details'])]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['payment:read', 'payment:details'])]
    private ?\DateTimeInterface $paidAt = null;

    #[ORM\Column(length: 20, enumType: PaymentStatus::class)]
    #[Groups(['payment:read', 'payment:details'])]
    private PaymentStatus $status = PaymentStatus::PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['payment:read', 'payment:create', 'payment:update', 'payment:details'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['payment:details'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updateStatus();
    }

    #[ORM\PreUpdate]
    public function updateStatusOnUpdate(): void
    {
        $this->updateStatus();
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

    public function getPaymentSchedule(): ?PaymentSchedule
    {
        return $this->paymentSchedule;
    }

    public function setPaymentSchedule(?PaymentSchedule $paymentSchedule): static
    {
        $this->paymentSchedule = $paymentSchedule;
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

    public function getAmountPaid(): ?string
    {
        return $this->amountPaid;
    }

    public function setAmountPaid(string $amountPaid): static
    {
        $this->amountPaid = $amountPaid;
        $this->updateStatus();
        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getPaidAt(): ?\DateTimeInterface
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeInterface $paidAt): static
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
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

    // Helper methods - LOGIQUE MÉTIER CRITIQUE POUR LES PAIEMENTS

    /**
     * Met à jour automatiquement le statut selon les montants et dates
     */
    private function updateStatus(): void
    {
        $amount = (float) $this->amount;
        $amountPaid = (float) $this->amountPaid;
        $now = new \DateTime();

        if ($amountPaid >= $amount) {
            $this->status = PaymentStatus::PAID;
            if (!$this->paidAt) {
                $this->paidAt = new \DateTime();
            }
        } elseif ($amountPaid > 0) {
            $this->status = PaymentStatus::PARTIAL;
        } elseif ($this->dueDate && $this->dueDate < $now) {
            $this->status = PaymentStatus::OVERDUE;
        } else {
            $this->status = PaymentStatus::PENDING;
        }
    }

    /**
     * Retourne le montant en float
     */
    #[Groups(['payment:read'])]
    public function getAmountFloat(): float
    {
        return (float) $this->amount;
    }

    /**
     * Retourne le montant payé en float
     */
    #[Groups(['payment:read'])]
    public function getAmountPaidFloat(): float
    {
        return (float) $this->amountPaid;
    }

    /**
     * Retourne le montant restant à payer
     */
    #[Groups(['payment:read'])]
    public function getRemainingAmount(): float
    {
        return $this->getAmountFloat() - $this->getAmountPaidFloat();
    }

    /**
     * Retourne le pourcentage payé
     */
    #[Groups(['payment:read'])]
    public function getPaymentPercentage(): float
    {
        $amount = $this->getAmountFloat();
        if ($amount === 0.0) {
            return 0.0;
        }
        return ($this->getAmountPaidFloat() / $amount) * 100;
    }

    /**
     * Vérifie si le paiement est en retard
     */
    #[Groups(['payment:read'])]
    public function isOverdue(): bool
    {
        $now = new \DateTime();
        return $this->dueDate < $now && $this->status !== PaymentStatus::PAID;
    }

    /**
     * Vérifie si le paiement est complètement payé
     */
    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::PAID;
    }

    /**
     * Vérifie si le paiement est partiellement payé
     */
    public function isPartial(): bool
    {
        return $this->status === PaymentStatus::PARTIAL;
    }

    /**
     * Vérifie si le paiement est en attente
     */
    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    /**
     * Retourne le libellé du statut
     */
    #[Groups(['payment:read'])]
    public function getStatusLabel(): string
    {
        return $this->status->getLabel();
    }

    /**
     * Ajoute un paiement partiel
     */
    public function addPayment(float $amount): static
    {
        $currentPaid = $this->getAmountPaidFloat();
        $newTotal = $currentPaid + $amount;
        $maxAmount = $this->getAmountFloat();

        if ($newTotal > $maxAmount) {
            $newTotal = $maxAmount;
        }

        $this->setAmountPaid((string) $newTotal);
        return $this;
    }

    /**
     * Retourne le nombre de jours de retard
     */
    #[Groups(['payment:read'])]
    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        $now = new \DateTime();
        return $this->dueDate->diff($now)->days;
    }

    public function __toString(): string
    {
        return sprintf(
            'Paiement %s - %s (%.2f€/%.2f€)',
            $this->user?->getFullName() ?? 'Unknown',
            $this->team?->getName() ?? 'Unknown',
            $this->getAmountPaidFloat(),
            $this->getAmountFloat()
        );
    }
} 