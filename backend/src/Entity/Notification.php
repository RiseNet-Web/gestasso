<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Put;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER') and user == object.getUser()",
            normalizationContext: ['groups' => ['notification:read']]
        ),
        new Get(
            security: "is_granted('ROLE_USER') and user == object.getUser()",
            normalizationContext: ['groups' => ['notification:read', 'notification:details']]
        ),
        new Put(
            security: "is_granted('ROLE_USER') and user == object.getUser()",
            denormalizationContext: ['groups' => ['notification:update']],
            normalizationContext: ['groups' => ['notification:read']]
        )
    ],
    normalizationContext: ['groups' => ['notification:read']],
    denormalizationContext: ['groups' => ['notification:update']]
)]
class Notification
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['notification:read', 'user:details'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'notifications')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['notification:read', 'notification:details'])]
    private ?User $user = null;

    #[ORM\Column(length: 50, enumType: NotificationType::class)]
    #[Groups(['notification:read', 'notification:details'])]
    private ?NotificationType $type = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Groups(['notification:read', 'notification:details'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le message est obligatoire.')]
    #[Groups(['notification:read', 'notification:details'])]
    private ?string $message = null;

    #[ORM\Column]
    #[Groups(['notification:read', 'notification:update'])]
    private ?bool $isRead = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['notification:details'])]
    private ?array $data = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['notification:read', 'notification:details'])]
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getType(): ?NotificationType
    {
        return $this->type;
    }

    public function setType(NotificationType $type): static
    {
        $this->type = $type;
        return $this;
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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function isRead(): ?bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): static
    {
        $this->data = $data;
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
     * Marque la notification comme lue
     */
    public function markAsRead(): static
    {
        $this->isRead = true;
        return $this;
    }

    /**
     * Marque la notification comme non lue
     */
    public function markAsUnread(): static
    {
        $this->isRead = false;
        return $this;
    }

    /**
     * Retourne le libellé du type
     */
    #[Groups(['notification:read'])]
    public function getTypeLabel(): string
    {
        return $this->type?->getLabel() ?? 'Inconnu';
    }

    /**
     * Vérifie si la notification est récente (moins de 24h)
     */
    #[Groups(['notification:read'])]
    public function isRecent(): bool
    {
        $now = new \DateTime();
        $dayAgo = (clone $now)->sub(new \DateInterval('P1D'));
        return $this->createdAt >= $dayAgo;
    }

    /**
     * Retourne l'âge de la notification en heures
     */
    #[Groups(['notification:details'])]
    public function getAgeInHours(): int
    {
        $now = new \DateTime();
        return $this->createdAt->diff($now)->h + ($this->createdAt->diff($now)->days * 24);
    }

    /**
     * Vérifie si c'est une notification de paiement
     */
    public function isPaymentNotification(): bool
    {
        return $this->type?->isPaymentNotification() ?? false;
    }

    /**
     * Vérifie si c'est une notification de document
     */
    public function isDocumentNotification(): bool
    {
        return $this->type?->isDocumentNotification() ?? false;
    }

    /**
     * Vérifie si c'est une notification de cagnotte
     */
    public function isCagnotteNotification(): bool
    {
        return $this->type?->isCagnotteNotification() ?? false;
    }

    /**
     * Retourne une donnée spécifique du tableau data
     */
    public function getDataValue(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Ajoute une donnée au tableau data
     */
    public function addData(string $key, mixed $value): static
    {
        if ($this->data === null) {
            $this->data = [];
        }
        $this->data[$key] = $value;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s (%s)',
            $this->getTypeLabel(),
            $this->title,
            $this->isRead ? 'Lu' : 'Non lu'
        );
    }
} 