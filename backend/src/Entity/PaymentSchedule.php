<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\PaymentScheduleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentScheduleRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['payment_schedule:read']]
        ),
        new Get(
            security: "is_granted('TEAM_VIEW', object.getTeam())",
            normalizationContext: ['groups' => ['payment_schedule:read', 'payment_schedule:details']]
        ),
        new Post(
            security: "is_granted('TEAM_EDIT', object.getTeam())",
            denormalizationContext: ['groups' => ['payment_schedule:create']],
            normalizationContext: ['groups' => ['payment_schedule:read']]
        ),
        new Put(
            security: "is_granted('TEAM_EDIT', object.getTeam())",
            denormalizationContext: ['groups' => ['payment_schedule:update']],
            normalizationContext: ['groups' => ['payment_schedule:read']]
        ),
        new Delete(
            security: "is_granted('TEAM_EDIT', object.getTeam())"
        )
    ],
    normalizationContext: ['groups' => ['payment_schedule:read']],
    denormalizationContext: ['groups' => ['payment_schedule:create']]
)]
class PaymentSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['payment_schedule:read', 'team:details', 'payment:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'paymentSchedules')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['payment_schedule:read', 'payment_schedule:details'])]
    private ?Team $team = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\Positive(message: 'Le montant doit être positif.')]
    #[Groups(['payment_schedule:read', 'payment_schedule:create', 'payment_schedule:update', 'payment:read'])]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date d\'échéance est obligatoire.')]
    #[Groups(['payment_schedule:read', 'payment_schedule:create', 'payment_schedule:update', 'payment:read'])]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['payment_schedule:read', 'payment_schedule:create', 'payment_schedule:update', 'payment:read'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['payment_schedule:details'])]
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

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;
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

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;
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
    #[Groups(['payment_schedule:read'])]
    public function getAmountFloat(): float
    {
        return (float) $this->amount;
    }

    /**
     * Vérifie si l'échéance est passée
     */
    #[Groups(['payment_schedule:read'])]
    public function isOverdue(): bool
    {
        $now = new \DateTime();
        return $this->dueDate < $now;
    }

    /**
     * Vérifie si l'échéance est dans les 7 prochains jours
     */
    #[Groups(['payment_schedule:read'])]
    public function isDueSoon(): bool
    {
        $now = new \DateTime();
        $weekFromNow = (clone $now)->add(new \DateInterval('P7D'));
        return $this->dueDate >= $now && $this->dueDate <= $weekFromNow;
    }

    /**
     * Retourne le nombre de jours jusqu'à l'échéance (négatif si passé)
     */
    #[Groups(['payment_schedule:read'])]
    public function getDaysUntilDue(): int
    {
        $now = new \DateTime();
        $diff = $now->diff($this->dueDate);
        return $diff->invert ? -$diff->days : $diff->days;
    }

    /**
     * Retourne le nom de l'équipe
     */
    #[Groups(['payment_schedule:read'])]
    public function getTeamName(): string
    {
        return $this->team?->getName() ?? 'Équipe inconnue';
    }

    /**
     * Retourne une description formatée
     */
    #[Groups(['payment_schedule:read'])]
    public function getFormattedDescription(): string
    {
        if ($this->description) {
            return $this->description;
        }
        return sprintf('Échéance du %s', $this->dueDate->format('d/m/Y'));
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %.2f€ (échéance: %s)',
            $this->getTeamName(),
            $this->getAmountFloat(),
            $this->dueDate->format('d/m/Y')
        );
    }
} 