<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use App\Repository\EventParticipantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventParticipantRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['event_participant:read']]
        ),
        new Get(
            security: "is_granted('EVENT_VIEW', object.getEvent())",
            normalizationContext: ['groups' => ['event_participant:read', 'event_participant:details']]
        ),
        new Post(
            security: "is_granted('EVENT_EDIT', object.getEvent())",
            denormalizationContext: ['groups' => ['event_participant:create']],
            normalizationContext: ['groups' => ['event_participant:read']]
        ),
        new Delete(
            security: "is_granted('EVENT_EDIT', object.getEvent())"
        )
    ],
    normalizationContext: ['groups' => ['event_participant:read']],
    denormalizationContext: ['groups' => ['event_participant:create']]
)]
class EventParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['event_participant:read', 'event:details'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'eventParticipants')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['event_participant:read', 'event_participant:details'])]
    private ?Event $event = null;

    #[ORM\ManyToOne(inversedBy: 'eventParticipations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['event_participant:read', 'event_participant:details', 'event:details'])]
    private ?User $user = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero(message: 'Le montant gagné doit être positif ou nul.')]
    #[Groups(['event_participant:read', 'event_participant:details'])]
    private ?string $amountEarned = '0.00';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['event_participant:details'])]
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

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): static
    {
        $this->event = $event;
        return $this;
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

    public function getAmountEarned(): ?string
    {
        return $this->amountEarned;
    }

    public function setAmountEarned(string $amountEarned): static
    {
        $this->amountEarned = $amountEarned;
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
     * Retourne le montant gagné en float
     */
    #[Groups(['event_participant:read'])]
    public function getAmountEarnedFloat(): float
    {
        return (float) $this->amountEarned;
    }

    /**
     * Vérifie si le participant a gagné de l'argent
     */
    public function hasEarnings(): bool
    {
        return $this->getAmountEarnedFloat() > 0;
    }

    /**
     * Met à jour le montant gagné
     */
    public function updateEarnings(float $amount): static
    {
        $this->amountEarned = (string) $amount;
        return $this;
    }

    /**
     * Retourne le nom complet du participant
     */
    #[Groups(['event_participant:read'])]
    public function getParticipantName(): string
    {
        return $this->user?->getFullName() ?? 'Utilisateur inconnu';
    }

    /**
     * Retourne l'email du participant
     */
    #[Groups(['event_participant:details'])]
    public function getParticipantEmail(): string
    {
        return $this->user?->getEmail() ?? '';
    }

    /**
     * Retourne le titre de l'événement
     */
    #[Groups(['event_participant:read'])]
    public function getEventTitle(): string
    {
        return $this->event?->getTitle() ?? 'Événement inconnu';
    }

    /**
     * Vérifie si l'événement est terminé
     */
    public function isEventCompleted(): bool
    {
        return $this->event?->getStatus() === Event::STATUS_COMPLETED;
    }

    /**
     * Vérifie si l'événement peut être distribué
     */
    public function canEventBeDistributed(): bool
    {
        return $this->event?->canBeDistributed() ?? false;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s (%.2f€)',
            $this->getParticipantName(),
            $this->getEventTitle(),
            $this->getAmountEarnedFloat()
        );
    }
} 