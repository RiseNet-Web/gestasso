<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Enum\DocumentStatus;
use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['document:read']],
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            normalizationContext: ['groups' => ['document:read', 'document:details']],
            security: "is_granted('DOCUMENT_VIEW', object)"
        ),
        new Post(
            denormalizationContext: ['groups' => ['document:create']],
            normalizationContext: ['groups' => ['document:read']],
            security: "is_granted('ROLE_USER')"
        ),
        new Put(
            denormalizationContext: ['groups' => ['document:update']],
            normalizationContext: ['groups' => ['document:read']],
            security: "is_granted('DOCUMENT_EDIT', object)"
        ),
        new Delete(
            security: "is_granted('DOCUMENT_DELETE', object)"
        )
    ]
)]
class Document
{

    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    public const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['document:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du document est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['document:read', 'document:create', 'document:update'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['document:read', 'document:details', 'document:create', 'document:update'])]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['document:read'])]
    private ?string $filePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['document:read'])]
    private ?string $originalFileName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['document:read'])]
    private ?string $mimeType = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['document:read'])]
    private ?int $fileSize = null;

    #[Vich\UploadableField(mapping: 'documents', fileNameProperty: 'filePath', originalName: 'originalFileName', mimeType: 'mimeType', size: 'fileSize')]
    #[Assert\File(
        maxSize: self::MAX_FILE_SIZE,
        mimeTypes: self::ALLOWED_MIME_TYPES,
        maxSizeMessage: 'Le fichier ne peut pas dépasser {{ limit }}',
        mimeTypesMessage: 'Le type de fichier "{{ type }}" n\'est pas autorisé. Les types autorisés sont : {{ types }}'
    )]
    #[Groups(['document:create', 'document:update'])]
    private ?File $documentFile = null;

    #[ORM\Column(length: 50, enumType: DocumentStatus::class)]
    #[Groups(['document:read', 'document:update'])]
    private DocumentStatus $status = DocumentStatus::PENDING;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['document:read', 'document:create', 'document:update'])]
    private ?\DateTimeInterface $expirationDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['document:read'])]
    private ?\DateTimeInterface $validatedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['document:read', 'document:details', 'document:update'])]
    private ?string $rejectionReason = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'utilisateur est obligatoire')]
    #[Groups(['document:read', 'document:details'])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le type de document est obligatoire')]
    #[Groups(['document:read', 'document:details'])]
    private ?DocumentType $documentType = null;

    #[ORM\ManyToOne]
    #[Groups(['document:read', 'document:details'])]
    private ?User $validatedBy = null;

    #[ORM\Column]
    #[Groups(['document:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column]
    #[Groups(['document:read'])]
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
        
        // Auto-update status based on expiration
        if ($this->isExpired() && $this->status === DocumentStatus::VALIDATED) {
            $this->status = DocumentStatus::EXPIRED;
        }
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

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getOriginalFileName(): ?string
    {
        return $this->originalFileName;
    }

    public function setOriginalFileName(?string $originalFileName): static
    {
        $this->originalFileName = $originalFileName;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getDocumentFile(): ?File
    {
        return $this->documentFile;
    }

    public function setDocumentFile(?File $documentFile = null): static
    {
        $this->documentFile = $documentFile;

        if ($documentFile) {
            $this->updatedAt = new \DateTime();
        }

        return $this;
    }

    public function getStatus(): DocumentStatus
    {
        return $this->status;
    }

    public function setStatus(DocumentStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?\DateTimeInterface $expirationDate): static
    {
        $this->expirationDate = $expirationDate;
        return $this;
    }

    public function getValidatedAt(): ?\DateTimeInterface
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeInterface $validatedAt): static
    {
        $this->validatedAt = $validatedAt;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
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

    public function getDocumentType(): ?DocumentType
    {
        return $this->documentType;
    }

    public function setDocumentType(?DocumentType $documentType): static
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $validatedBy): static
    {
        $this->validatedBy = $validatedBy;
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

    public function getStatusLabel(): string
    {
        return $this->status->getLabel();
    }

    public function isPending(): bool
    {
        return $this->status === DocumentStatus::PENDING;
    }

    public function isValidated(): bool
    {
        return $this->status === DocumentStatus::VALIDATED;
    }

    public function isRejected(): bool
    {
        return $this->status === DocumentStatus::REJECTED;
    }

    public function isExpired(): bool
    {
        if (!$this->expirationDate) {
            return false;
        }
        
        return $this->expirationDate < new \DateTime();
    }

    public function isExpiringSoon(int $daysThreshold = 30): bool
    {
        if (!$this->expirationDate || $this->isExpired()) {
            return false;
        }

        $threshold = new \DateTime();
        $threshold->add(new \DateInterval("P{$daysThreshold}D"));
        
        return $this->expirationDate <= $threshold;
    }

    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->expirationDate) {
            return null;
        }

        $now = new \DateTime();
        $diff = $now->diff($this->expirationDate);
        
        return $diff->invert ? -$diff->days : $diff->days;
    }

    public function validate(User $validatedBy): static
    {
        $this->status = DocumentStatus::VALIDATED;
        $this->validatedBy = $validatedBy;
        $this->validatedAt = new \DateTime();
        $this->rejectionReason = null;
        
        // Set expiration date if document type has validity duration
        if ($this->documentType && $this->documentType->isExpirable()) {
            $this->expirationDate = new \DateTime();
            $this->expirationDate->add(new \DateInterval("P{$this->documentType->getValidityDurationInDays()}D"));
        }
        
        return $this;
    }

    public function reject(string $reason, User $rejectedBy): static
    {
        $this->status = DocumentStatus::REJECTED;
        $this->rejectionReason = $reason;
        $this->validatedBy = $rejectedBy;
        $this->validatedAt = new \DateTime();
        $this->expirationDate = null;
        
        return $this;
    }

    public function resetTopending(): static
    {
        $this->status = DocumentStatus::PENDING;
        $this->validatedBy = null;
        $this->validatedAt = null;
        $this->rejectionReason = null;
        $this->expirationDate = null;
        
        return $this;
    }

    public function getFileSizeFormatted(): string
    {
        if (!$this->fileSize) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->fileSize;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    public function isImage(): bool
    {
        return $this->mimeType && str_starts_with($this->mimeType, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }

    public function getFileExtension(): ?string
    {
        if (!$this->originalFileName) {
            return null;
        }
        
        return strtolower(pathinfo($this->originalFileName, PATHINFO_EXTENSION));
    }

    public function hasFile(): bool
    {
        return !empty($this->filePath);
    }

    public function getAgeInDays(): int
    {
        $now = new \DateTime();
        $diff = $this->createdAt->diff($now);
        
        return $diff->days;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
} 