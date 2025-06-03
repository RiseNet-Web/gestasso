<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Un utilisateur avec cet email existe déjà.')]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['user:read']]
        ),
        new Get(
            security: "is_granted('ROLE_USER') and (object == user or is_granted('ROLE_CLUB_MANAGER'))",
            normalizationContext: ['groups' => ['user:read', 'user:details']]
        ),
        new Post(
            denormalizationContext: ['groups' => ['user:create']],
            normalizationContext: ['groups' => ['user:read']]
        ),
        new Put(
            security: "is_granted('ROLE_USER') and object == user",
            denormalizationContext: ['groups' => ['user:update']],
            normalizationContext: ['groups' => ['user:read']]
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN') or object == user"
        )
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:create']]
)]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read', 'club:read', 'team:read', 'payment:read', 'event:read', 'document:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'L\'email doit être valide.')]
    #[Groups(['user:read', 'user:create', 'user:update', 'club:read', 'team:read'])]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups(['user:details'])]
    private array $roles = [];

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['user:read', 'user:create', 'user:update', 'club:read', 'team:read'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['user:read', 'user:create', 'user:update', 'club:read', 'team:read'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9+\-\s()]+$/', message: 'Le numéro de téléphone n\'est pas valide.')]
    #[Groups(['user:read', 'user:create', 'user:update', 'user:details'])]
    private ?string $phone = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['user:read', 'user:create', 'user:update', 'user:details'])]
    private ?\DateTimeInterface $dateOfBirth = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(choices: ['owner', 'member'], message: 'Le type d\'onboarding doit être "owner" ou "member".')]
    #[Groups(['user:read', 'user:create', 'user:update', 'user:details'])]
    private ?string $onboardingType = null;

    #[ORM\Column]
    #[Groups(['user:read', 'user:details'])]
    private bool $onboardingCompleted = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['user:details'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['user:details'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column]
    #[Groups(['user:details'])]
    private ?bool $isActive = true;

    // Relations

    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Club::class)]
    #[Groups(['user:details'])]
    private Collection $ownedClubs;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ClubManager::class)]
    private Collection $clubManagers;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TeamMember::class)]
    private Collection $teamMemberships;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Payment::class)]
    private Collection $payments;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Cagnotte::class)]
    private Collection $cagnottes;

    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: Event::class)]
    private Collection $createdEvents;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: EventParticipant::class)]
    private Collection $eventParticipations;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Document::class)]
    private Collection $documents;

    #[ORM\OneToMany(mappedBy: 'validatedBy', targetEntity: Document::class)]
    private Collection $validatedDocuments;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class)]
    private Collection $notifications;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserAuthentication::class)]
    private Collection $userAuthentications;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: JoinRequest::class)]
    private Collection $joinRequests;

    #[ORM\OneToMany(mappedBy: 'reviewedBy', targetEntity: JoinRequest::class)]
    private Collection $reviewedJoinRequests;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: RefreshToken::class, cascade: ['remove'])]
    private Collection $refreshTokens;

    public function __construct()
    {
        $this->ownedClubs = new ArrayCollection();
        $this->clubManagers = new ArrayCollection();
        $this->teamMemberships = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->cagnottes = new ArrayCollection();
        $this->createdEvents = new ArrayCollection();
        $this->eventParticipations = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->validatedDocuments = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->userAuthentications = new ArrayCollection();
        $this->joinRequests = new ArrayCollection();
        $this->reviewedJoinRequests = new ArrayCollection();
        $this->refreshTokens = new ArrayCollection();
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // Rien à effacer car pas de données sensibles stockées dans User
    }
    
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getDateOfBirth(): ?\DateTimeInterface
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(?\DateTimeInterface $dateOfBirth): static
    {
        $this->dateOfBirth = $dateOfBirth;
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

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getOnboardingType(): ?string
    {
        return $this->onboardingType;
    }

    public function setOnboardingType(?string $onboardingType): static
    {
        $this->onboardingType = $onboardingType;
        return $this;
    }

    public function isOnboardingCompleted(): bool
    {
        return $this->onboardingCompleted;
    }

    public function setOnboardingCompleted(bool $onboardingCompleted): static
    {
        $this->onboardingCompleted = $onboardingCompleted;
        return $this;
    }

    #[Groups(['user:read'])]
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    /**
     * Calcule l'âge actuel de l'utilisateur
     */
    #[Groups(['user:read', 'user:details'])]
    public function getAge(): ?int
    {
        if (!$this->dateOfBirth) {
            return null;
        }
        
        $now = new \DateTime();
        $interval = $this->dateOfBirth->diff($now);
        
        return $interval->y;
    }

    /**
     * Retourne l'année de naissance de l'utilisateur
     */
    #[Groups(['user:read', 'user:details'])]
    public function getBirthYear(): ?int
    {
        if (!$this->dateOfBirth) {
            return null;
        }
        
        return (int) $this->dateOfBirth->format('Y');
    }

    /**
     * Vérifie si l'utilisateur respecte les restrictions d'âge d'une équipe
     */
    public function meetsAgeRestrictions(?int $minBirthYear, ?int $maxBirthYear): bool
    {
        $birthYear = $this->getBirthYear();
        
        if ($birthYear === null) {
            return false; // Pas de date de naissance
        }
        
        // Vérifier la restriction d'âge minimum (maxBirthYear)
        if ($maxBirthYear !== null && $birthYear > $maxBirthYear) {
            return false; // Trop jeune
        }
        
        // Vérifier la restriction d'âge maximum (minBirthYear)
        if ($minBirthYear !== null && $birthYear < $minBirthYear) {
            return false; // Trop âgé
        }
        
        return true;
    }

    // Collection methods

    /**
     * @return Collection<int, Club>
     */
    public function getOwnedClubs(): Collection
    {
        return $this->ownedClubs;
    }

    public function addOwnedClub(Club $ownedClub): static
    {
        if (!$this->ownedClubs->contains($ownedClub)) {
            $this->ownedClubs->add($ownedClub);
            $ownedClub->setOwner($this);
        }
        return $this;
    }

    public function removeOwnedClub(Club $ownedClub): static
    {
        if ($this->ownedClubs->removeElement($ownedClub)) {
            if ($ownedClub->getOwner() === $this) {
                $ownedClub->setOwner(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ClubManager>
     */
    public function getClubManagers(): Collection
    {
        return $this->clubManagers;
    }

    public function addClubManager(ClubManager $clubManager): static
    {
        if (!$this->clubManagers->contains($clubManager)) {
            $this->clubManagers->add($clubManager);
            $clubManager->setUser($this);
        }
        return $this;
    }

    public function removeClubManager(ClubManager $clubManager): static
    {
        if ($this->clubManagers->removeElement($clubManager)) {
            if ($clubManager->getUser() === $this) {
                $clubManager->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, TeamMember>
     */
    public function getTeamMemberships(): Collection
    {
        return $this->teamMemberships;
    }

    public function addTeamMembership(TeamMember $teamMembership): static
    {
        if (!$this->teamMemberships->contains($teamMembership)) {
            $this->teamMemberships->add($teamMembership);
            $teamMembership->setUser($this);
        }
        return $this;
    }

    public function removeTeamMembership(TeamMember $teamMembership): static
    {
        if ($this->teamMemberships->removeElement($teamMembership)) {
            if ($teamMembership->getUser() === $this) {
                $teamMembership->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setUser($this);
        }
        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getUser() === $this) {
                $payment->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Cagnotte>
     */
    public function getCagnottes(): Collection
    {
        return $this->cagnottes;
    }

    public function addCagnotte(Cagnotte $cagnotte): static
    {
        if (!$this->cagnottes->contains($cagnotte)) {
            $this->cagnottes->add($cagnotte);
            $cagnotte->setUser($this);
        }
        return $this;
    }

    public function removeCagnotte(Cagnotte $cagnotte): static
    {
        if ($this->cagnottes->removeElement($cagnotte)) {
            if ($cagnotte->getUser() === $this) {
                $cagnotte->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getCreatedEvents(): Collection
    {
        return $this->createdEvents;
    }

    public function addCreatedEvent(Event $createdEvent): static
    {
        if (!$this->createdEvents->contains($createdEvent)) {
            $this->createdEvents->add($createdEvent);
            $createdEvent->setCreatedBy($this);
        }
        return $this;
    }

    public function removeCreatedEvent(Event $createdEvent): static
    {
        if ($this->createdEvents->removeElement($createdEvent)) {
            if ($createdEvent->getCreatedBy() === $this) {
                $createdEvent->setCreatedBy(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, EventParticipant>
     */
    public function getEventParticipations(): Collection
    {
        return $this->eventParticipations;
    }

    public function addEventParticipation(EventParticipant $eventParticipation): static
    {
        if (!$this->eventParticipations->contains($eventParticipation)) {
            $this->eventParticipations->add($eventParticipation);
            $eventParticipation->setUser($this);
        }
        return $this;
    }

    public function removeEventParticipation(EventParticipant $eventParticipation): static
    {
        if ($this->eventParticipations->removeElement($eventParticipation)) {
            if ($eventParticipation->getUser() === $this) {
                $eventParticipation->setUser(null);
            }
        }
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
            $document->setUser($this);
        }
        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getUser() === $this) {
                $document->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getValidatedDocuments(): Collection
    {
        return $this->validatedDocuments;
    }

    public function addValidatedDocument(Document $validatedDocument): static
    {
        if (!$this->validatedDocuments->contains($validatedDocument)) {
            $this->validatedDocuments->add($validatedDocument);
            $validatedDocument->setValidatedBy($this);
        }
        return $this;
    }

    public function removeValidatedDocument(Document $validatedDocument): static
    {
        if ($this->validatedDocuments->removeElement($validatedDocument)) {
            if ($validatedDocument->getValidatedBy() === $this) {
                $validatedDocument->setValidatedBy(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }
        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }
        return $this;
    }

    // Helper methods for roles
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    public function addRole(string $role): static
    {
        if (!in_array($role, $this->roles)) {
            $this->roles[] = $role;
        }
        return $this;
    }

    public function removeRole(string $role): static
    {
        $this->roles = array_diff($this->roles, [$role]);
        return $this;
    }

    /**
     * @return Collection<int, RefreshToken>
     */
    public function getRefreshTokens(): Collection
    {
        return $this->refreshTokens;
    }

    public function addRefreshToken(RefreshToken $refreshToken): static
    {
        if (!$this->refreshTokens->contains($refreshToken)) {
            $this->refreshTokens->add($refreshToken);
            $refreshToken->setUser($this);
        }
        return $this;
    }

    public function removeRefreshToken(RefreshToken $refreshToken): static
    {
        if ($this->refreshTokens->removeElement($refreshToken)) {
            if ($refreshToken->getUser() === $this) {
                $refreshToken->setUser(null);
            }
        }
        return $this;
    }
} 