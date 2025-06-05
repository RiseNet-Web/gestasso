<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\TeamRepository;
use App\Enum\TeamMemberRole;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Assert\Callback([Team::class, 'validateAgeRestrictionsCallback'])]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['team:read']]
        ),
        new Get(
            security: "is_granted('TEAM_VIEW', object)",
            normalizationContext: ['groups' => ['team:read', 'team:details']]
        ),
        new Post(
            security: "is_granted('CLUB_EDIT', object.getClub())",
            denormalizationContext: ['groups' => ['team:create']],
            normalizationContext: ['groups' => ['team:read']]
        ),
        new Put(
            security: "is_granted('TEAM_EDIT', object)",
            denormalizationContext: ['groups' => ['team:update']],
            normalizationContext: ['groups' => ['team:read']]
        ),
        new Delete(
            security: "is_granted('TEAM_EDIT', object)"
        )
    ],
    normalizationContext: ['groups' => ['team:read']],
    denormalizationContext: ['groups' => ['team:create']]
)]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['team:read', 'club:details', 'season:details', 'user:details'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de l\'équipe est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    #[Groups(['team:read', 'team:create', 'team:update', 'club:details', 'season:details'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['team:read', 'team:create', 'team:update', 'team:details'])]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['team:read', 'team:create', 'team:update', 'team:details'])]
    private ?string $imagePath = null;

    #[ORM\ManyToOne(inversedBy: 'teams')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['team:read', 'team:details'])]
    private ?Club $club = null;

    #[ORM\ManyToOne(inversedBy: 'teams')]
    #[Groups(['team:read', 'team:details'])]
    private ?Season $season = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le prix annuel doit être positif ou nul.')]
    #[Groups(['team:read', 'team:create', 'team:update', 'team:details'])]
    private ?string $annualPrice = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(choices: ['male', 'female', 'mixed'], message: 'Le genre doit être male, female ou mixed.')]
    #[Groups(['team:read', 'team:create', 'team:update', 'team:details'])]
    private ?string $gender = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['team:read', 'team:create', 'team:update', 'team:details'])]
    private ?int $minBirthYear = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['team:read', 'team:create', 'team:update', 'team:details'])]
    private ?int $maxBirthYear = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['team:details'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['team:details'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column]
    #[Groups(['team:details'])]
    private ?bool $isActive = true;

    // Relations

    #[ORM\OneToMany(mappedBy: 'team', targetEntity: TeamMember::class, cascade: ['persist', 'remove'])]
    #[Groups(['team:details'])]
    private Collection $teamMembers;

    #[ORM\OneToMany(mappedBy: 'team', targetEntity: DocumentType::class, cascade: ['persist', 'remove'])]
    #[Groups(['team:details'])]
    private Collection $documentTypes;

    #[ORM\OneToMany(mappedBy: 'team', targetEntity: JoinRequest::class)]
    private Collection $joinRequests;

    public function __construct()
    {
        $this->teamMembers = new ArrayCollection();
        $this->documentTypes = new ArrayCollection();
        $this->joinRequests = new ArrayCollection();
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

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;
        return $this;
    }

    public function getClub(): ?Club
    {
        return $this->club;
    }

    public function setClub(?Club $club): static
    {
        $this->club = $club;
        return $this;
    }

    public function getSeason(): ?Season
    {
        return $this->season;
    }

    public function setSeason(?Season $season): static
    {
        $this->season = $season;
        return $this;
    }

    public function getAnnualPrice(): ?string
    {
        return $this->annualPrice;
    }

    public function setAnnualPrice(?string $annualPrice): static
    {
        $this->annualPrice = $annualPrice;
        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): static
    {
        $this->gender = $gender;
        return $this;
    }

    public function getMinBirthYear(): ?int
    {
        return $this->minBirthYear;
    }

    public function setMinBirthYear(?int $minBirthYear): static
    {
        $this->minBirthYear = $minBirthYear;
        return $this;
    }

    public function getMaxBirthYear(): ?int
    {
        return $this->maxBirthYear;
    }

    public function setMaxBirthYear(?int $maxBirthYear): static
    {
        $this->maxBirthYear = $maxBirthYear;
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

    // Collection methods

    /**
     * @return Collection<int, TeamMember>
     */
    public function getTeamMembers(): Collection
    {
        return $this->teamMembers;
    }

    public function addTeamMember(TeamMember $teamMember): static
    {
        if (!$this->teamMembers->contains($teamMember)) {
            $this->teamMembers->add($teamMember);
            $teamMember->setTeam($this);
        }
        return $this;
    }

    public function removeTeamMember(TeamMember $teamMember): static
    {
        if ($this->teamMembers->removeElement($teamMember)) {
            if ($teamMember->getTeam() === $this) {
                $teamMember->setTeam(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, DocumentType>
     */
    public function getDocumentTypes(): Collection
    {
        return $this->documentTypes;
    }

    public function addDocumentType(DocumentType $documentType): static
    {
        if (!$this->documentTypes->contains($documentType)) {
            $this->documentTypes->add($documentType);
            $documentType->setTeam($this);
        }
        return $this;
    }

    public function removeDocumentType(DocumentType $documentType): static
    {
        if ($this->documentTypes->removeElement($documentType)) {
            if ($documentType->getTeam() === $this) {
                $documentType->setTeam(null);
            }
        }
        return $this;
    }

    // Helper methods

    /**
     * Retourne les membres actifs de l'équipe
     */
    public function getActiveMembers(): Collection
    {
        return $this->teamMembers->filter(function(TeamMember $member) {
            return $member->isActive();
        });
    }

    /**
     * Retourne les athlètes de l'équipe (rôle ROLE_ATHLETE)
     */
    public function getAthletes(): Collection
    {
        return $this->teamMembers->filter(function(TeamMember $member) {
            return $member->getRole() === TeamMemberRole::ATHLETE && $member->isActive();
        });
    }

    /**
     * Retourne les coaches de l'équipe (rôle ROLE_COACH)
     */
    public function getCoaches(): Collection
    {
        return $this->teamMembers->filter(function(TeamMember $member) {
            return $member->getRole() === TeamMemberRole::COACH && $member->isActive();
        });
    }

    /**
     * Vérifie si un utilisateur est membre de l'équipe
     */
    public function hasMember(User $user): bool
    {
        foreach ($this->teamMembers as $member) {
            if ($member->getUser() === $user && $member->isActive()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifie si un utilisateur est coach de l'équipe
     */
    public function hasCoach(User $user): bool
    {
        foreach ($this->teamMembers as $member) {
            if ($member->getUser() === $user && $member->getRole() === TeamMemberRole::COACH && $member->isActive()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retourne le nombre total de membres actifs
     */
    #[Groups(['team:read'])]
    public function getMemberCount(): int
    {
        return $this->getActiveMembers()->count();
    }

    /**
     * Retourne le nombre d'athlètes actifs
     */
    #[Groups(['team:read'])]
    public function getAthleteCount(): int
    {
        return $this->getAthletes()->count();
    }



    /**
     * Retourne les types de documents requis
     */
    public function getRequiredDocumentTypes(): Collection
    {
        return $this->documentTypes->filter(function(DocumentType $type) {
            return $type->isRequired();
        });
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    /**
     * Vérifie si un utilisateur respecte les restrictions d'âge de l'équipe
     */
    public function userMeetsAgeRestrictions(User $user, string $role = 'athlete'): bool
    {
        // Les coachs ne sont pas soumis aux restrictions d'âge
        if ($role === 'coach' || in_array('ROLE_COACH', $user->getRoles())) {
            return true;
        }

        return $user->meetsAgeRestrictions($this->minBirthYear, $this->maxBirthYear);
    }

    /**
     * Retourne la tranche d'âge de l'équipe sous forme de chaîne lisible
     */
    #[Groups(['team:read', 'team:details'])]
    public function getAgeRange(): ?string
    {
        if ($this->minBirthYear === null && $this->maxBirthYear === null) {
            return null; // Pas de restriction d'âge
        }

        $currentYear = (int) date('Y');
        
        $minAge = $this->maxBirthYear ? $currentYear - $this->maxBirthYear : null;
        $maxAge = $this->minBirthYear ? $currentYear - $this->minBirthYear : null;

        if ($minAge && $maxAge) {
            return $minAge . '-' . $maxAge . ' ans';
        } elseif ($minAge) {
            return $minAge . ' ans et plus';
        } elseif ($maxAge) {
            return 'jusqu\'à ' . $maxAge . ' ans';
        }

        return null;
    }

    /**
     * Valide la cohérence des restrictions d'âge
     */
    public function validateAgeRestrictions(): array
    {
        $errors = [];

        if ($this->minBirthYear !== null && $this->maxBirthYear !== null) {
            if ($this->minBirthYear > $this->maxBirthYear) {
                $errors[] = 'L\'année de naissance minimum ne peut pas être supérieure à l\'année de naissance maximum.';
            }
        }

        return $errors;
    }

    /**
     * Retourne un message d'erreur personnalisé pour la restriction d'âge
     */
    public function getAgeRestrictionErrorMessage(User $user): ?string
    {
        $birthYear = $user->getBirthYear();
        
        if ($birthYear === null) {
            return 'Une date de naissance est obligatoire pour rejoindre cette équipe.';
        }

        if ($this->maxBirthYear !== null && $birthYear > $this->maxBirthYear) {
            $currentYear = (int) date('Y');
            $maxAge = $currentYear - $this->maxBirthYear;
            return "Vous êtes trop jeune pour cette équipe. Âge maximum : {$maxAge} ans.";
        }

        if ($this->minBirthYear !== null && $birthYear < $this->minBirthYear) {
            $currentYear = (int) date('Y');
            $minAge = $currentYear - $this->minBirthYear;
            return "Vous êtes trop âgé pour cette équipe. Âge minimum : {$minAge} ans.";
        }

        return null;
    }

    /**
     * Méthode de callback pour la validation des restrictions d'âge
     */
    public static function validateAgeRestrictionsCallback($object, \Symfony\Component\Validator\Context\ExecutionContextInterface $context, $payload)
    {
        if ($object instanceof Team) {
            $errors = $object->validateAgeRestrictions();
            
            foreach ($errors as $error) {
                $context->buildViolation($error)
                    ->atPath('minBirthYear')
                    ->addViolation();
            }
        }
    }
} 