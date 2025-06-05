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
#[ORM\Table(name: 'documents')]
#[ORM\Index(columns: ['document_type'], name: 'idx_document_type')]
#[ORM\Index(columns: ['is_confidential'], name: 'idx_confidential')]
#[ORM\Index(columns: ['uploaded_at'], name: 'idx_uploaded_at')]
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

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'utilisateur est obligatoire')]
    #[Groups(['document:read', 'document:details'])]
    private User $uploadedBy;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['document:read', 'document:details'])]
    private ?User $relatedUser = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['document:read', 'document:details'])]
    private string $originalName; // Chiffré pour confidentialité

    #[ORM\Column(type: 'string', length: 500)]
    #[Groups(['document:read', 'document:details'])]
    private string $securePath; // Chemin dans le stockage sécurisé

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups(['document:read', 'document:details'])]
    private string $mimeType;

    #[ORM\Column(type: 'integer')]
    #[Groups(['document:read', 'document:details'])]
    private int $fileSize;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        'passport', 'identity_card', 'license', 'certificate', 
        'medical_document', 'insurance', 'contract', 'invoice',
        'photo', 'other'
    ])]
    #[Groups(['document:read', 'document:details'])]
    private string $documentType;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['document:read', 'document:details'])]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['document:read', 'document:details'])]
    private bool $isConfidential = true; // Par défaut, tous les documents sont confidentiels

    #[ORM\Column(type: 'datetime')]
    #[Groups(['document:read', 'document:details'])]
    private \DateTime $uploadedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['document:read', 'document:details'])]
    private ?\DateTime $lastAccessedAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['document:read', 'document:details'])]
    private int $accessCount = 0;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['document:read', 'document:details'])]
    private ?string $accessToken = null; // Token temporaire pour accès sécurisé

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['document:read', 'document:details'])]
    private ?\DateTime $accessTokenExpiry = null;

    // Métadonnées de sécurité
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['document:read', 'document:details'])]
    private ?array $securityMetadata = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['document:read', 'document:details'])]
    private bool $isActive = true;

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

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['document:read'])]
    private ?string $filePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['document:read'])]
    private ?string $originalFileName = null;

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

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['document:read', 'document:details', 'document:update'])]
    private ?string $validationNotes = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['document:read', 'document:details'])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le type de document est obligatoire')]
    #[Groups(['document:read', 'document:details'])]
    private ?DocumentType $documentTypeEntity = null;

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

    public function getUploadedBy(): User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(User $uploadedBy): self
    {
        $this->uploadedBy = $uploadedBy;
        return $this;
    }

    public function getRelatedUser(): ?User
    {
        return $this->relatedUser;
    }

    public function setRelatedUser(?User $relatedUser): self
    {
        $this->relatedUser = $relatedUser;
        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): self
    {
        $this->originalName = $originalName;
        return $this;
    }

    public function getSecurePath(): string
    {
        return $this->securePath;
    }

    public function setSecurePath(string $securePath): self
    {
        $this->securePath = $securePath;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): self
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function setDocumentType(string $documentType): self
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function isConfidential(): bool
    {
        return $this->isConfidential;
    }

    public function setIsConfidential(bool $isConfidential): self
    {
        $this->isConfidential = $isConfidential;
        return $this;
    }

    public function getUploadedAt(): \DateTime
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTime $uploadedAt): self
    {
        $this->uploadedAt = $uploadedAt;
        return $this;
    }

    public function getLastAccessedAt(): ?\DateTime
    {
        return $this->lastAccessedAt;
    }

    public function setLastAccessedAt(?\DateTime $lastAccessedAt): self
    {
        $this->lastAccessedAt = $lastAccessedAt;
        return $this;
    }

    public function getAccessCount(): int
    {
        return $this->accessCount;
    }

    public function incrementAccessCount(): self
    {
        $this->accessCount++;
        $this->lastAccessedAt = new \DateTime();
        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): self
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    public function getAccessTokenExpiry(): ?\DateTime
    {
        return $this->accessTokenExpiry;
    }

    public function setAccessTokenExpiry(?\DateTime $accessTokenExpiry): self
    {
        $this->accessTokenExpiry = $accessTokenExpiry;
        return $this;
    }

    public function getSecurityMetadata(): ?array
    {
        return $this->securityMetadata;
    }

    public function setSecurityMetadata(?array $securityMetadata): self
    {
        $this->securityMetadata = $securityMetadata;
        return $this;
    }

    public function addSecurityMetadata(string $key, mixed $value): self
    {
        if ($this->securityMetadata === null) {
            $this->securityMetadata = [];
        }
        
        $this->securityMetadata[$key] = $value;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * Vérifie si le token d'accès est valide
     */
    public function isAccessTokenValid(string $token): bool
    {
        if ($this->accessToken === null || $this->accessTokenExpiry === null) {
            return false;
        }

        if ($this->accessTokenExpiry < new \DateTime()) {
            return false;
        }

        return hash_equals($this->accessToken, $token);
    }

    /**
     * Génère un token d'accès temporaire sécurisé
     */
    public function generateSecureAccessToken(int $validityMinutes = 30): string
    {
        $token = bin2hex(random_bytes(32));
        $this->accessToken = hash('sha256', $token);
        $this->accessTokenExpiry = (new \DateTime())->add(new \DateInterval("PT{$validityMinutes}M"));
        
        return $token; // Retourner le token non hashé pour l'utilisateur
    }

    /**
     * Invalide le token d'accès
     */
    public function invalidateAccessToken(): self
    {
        $this->accessToken = null;
        $this->accessTokenExpiry = null;
        return $this;
    }

    /**
     * Retourne une version publique sécurisée de l'entité
     */
    public function toSecureArray(): array
    {
        return [
            'id' => $this->id,
            'documentType' => $this->documentType,
            'description' => $this->description,
            'fileSize' => $this->fileSize,
            'mimeType' => $this->mimeType,
            'uploadedAt' => $this->uploadedAt->format('c'),
            'isConfidential' => $this->isConfidential,
            'accessCount' => $this->accessCount,
            'lastAccessedAt' => $this->lastAccessedAt?->format('c'),
            // Ne pas exposer : originalName (chiffré), securePath, accessToken
        ];
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

    public function getValidationNotes(): ?string
    {
        return $this->validationNotes;
    }

    public function setValidationNotes(?string $validationNotes): static
    {
        $this->validationNotes = $validationNotes;
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

    public function getDocumentTypeEntity(): ?DocumentType
    {
        return $this->documentTypeEntity;
    }

    public function setDocumentTypeEntity(?DocumentType $documentTypeEntity): static
    {
        $this->documentTypeEntity = $documentTypeEntity;
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
        return $this->status === DocumentStatus::APPROVED;
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
        $this->status = DocumentStatus::APPROVED;
        $this->validatedBy = $validatedBy;
        $this->validatedAt = new \DateTime();
        $this->rejectionReason = null;
        
        // Set expiration date if document type has validity duration
        if ($this->documentTypeEntity && $this->documentTypeEntity->isExpirable()) {
            $this->expirationDate = new \DateTime();
            $this->expirationDate->add(new \DateInterval("P{$this->documentTypeEntity->getValidityDurationInDays()}D"));
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