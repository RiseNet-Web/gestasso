<?php

namespace App\Entity;

use App\Enum\DocumentStatus;
use App\Enum\DocumentType as DocumentTypeEnum;
use App\Repository\DocumentTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentTypeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class DocumentType
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['document_type:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du type de document est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['document_type:read', 'document_type:create', 'document_type:update'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['document_type:read', 'document_type:details', 'document_type:create', 'document_type:update'])]
    private ?string $description = null;

    #[ORM\Column(length: 50, enumType: DocumentTypeEnum::class)]
    #[Assert\NotNull(message: 'Le type est obligatoire')]
    #[Groups(['document_type:read', 'document_type:create', 'document_type:update'])]
    private ?DocumentTypeEnum $type = null;

    #[ORM\Column]
    #[Groups(['document_type:read', 'document_type:create', 'document_type:update'])]
    private bool $isRequired = true;

    #[ORM\Column]
    #[Groups(['document_type:read', 'document_type:create', 'document_type:update'])]
    private bool $hasExpirationDate = false;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'La durée de validité doit être positive')]
    #[Groups(['document_type:read', 'document_type:create', 'document_type:update'])]
    private ?int $validityDurationInDays = null;

    #[ORM\ManyToOne(inversedBy: 'documentTypes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'équipe est obligatoire')]
    #[Groups(['document_type:read', 'document_type:details'])]
    private ?Team $team = null;

    #[ORM\Column]
    #[Groups(['document_type:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['document_type:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['document_type:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'documentType', cascade: ['remove'])]
    #[Groups(['document_type:details'])]
    private Collection $documents;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
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

    public function getType(): ?DocumentTypeEnum
    {
        return $this->type;
    }

    public function setType(DocumentTypeEnum $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): static
    {
        $this->isRequired = $isRequired;
        return $this;
    }

    public function hasExpirationDate(): bool
    {
        return $this->hasExpirationDate;
    }

    public function setHasExpirationDate(bool $hasExpirationDate): static
    {
        $this->hasExpirationDate = $hasExpirationDate;
        return $this;
    }

    public function getValidityDurationInDays(): ?int
    {
        return $this->validityDurationInDays;
    }

    public function setValidityDurationInDays(?int $validityDurationInDays): static
    {
        $this->validityDurationInDays = $validityDurationInDays;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setDocumentType($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getDocumentType() === $this) {
                $document->setDocumentType(null);
            }
        }

        return $this;
    }

    // Méthodes helper

    public function getTypeLabel(): string
    {
        return $this->type?->getLabel() ?? 'Inconnu';
    }

    public function isExpirable(): bool
    {
        return $this->hasExpirationDate && $this->validityDurationInDays > 0;
    }

    public function getValidityDurationInMonths(): ?float
    {
        if (!$this->validityDurationInDays) {
            return null;
        }
        return round($this->validityDurationInDays / 30.44, 1); // Moyenne de jours par mois
    }

    public function getValidityDurationInYears(): ?float
    {
        if (!$this->validityDurationInDays) {
            return null;
        }
        return round($this->validityDurationInDays / 365.25, 1); // Année bissextile
    }

    public function getDocumentCount(): int
    {
        return $this->documents->count();
    }

    public function getValidDocuments(): Collection
    {
        return $this->documents->filter(function (Document $document) {
            return $document->getStatus() === DocumentStatus::VALIDATED;
        });
    }

    public function getPendingDocuments(): Collection
    {
        return $this->documents->filter(function (Document $document) {
            return $document->getStatus() === DocumentStatus::PENDING;
        });
    }

    public function getExpiredDocuments(): Collection
    {
        if (!$this->isExpirable()) {
            return new ArrayCollection();
        }

        return $this->documents->filter(function (Document $document) {
            return $document->isExpired();
        });
    }

    public function getExpiringDocuments(int $daysThreshold = 30): Collection
    {
        if (!$this->isExpirable()) {
            return new ArrayCollection();
        }

        return $this->documents->filter(function (Document $document) use ($daysThreshold) {
            return $document->isExpiringSoon($daysThreshold);
        });
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
} 