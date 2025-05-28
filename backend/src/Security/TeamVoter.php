<?php

namespace App\Security;

use App\Entity\Team;
use App\Entity\User;
use App\Entity\TeamMember;
use App\Entity\ClubManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TeamVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';
    public const MANAGE_MEMBERS = 'manage_members';
    public const MANAGE_PAYMENTS = 'manage_payments';
    public const MANAGE_DOCUMENTS = 'manage_documents';
    public const CREATE_EVENT = 'create_event';

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW, self::EDIT, self::DELETE, self::MANAGE_MEMBERS,
            self::MANAGE_PAYMENTS, self::MANAGE_DOCUMENTS, self::CREATE_EVENT
        ]) && $subject instanceof Team;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Team $team */
        $team = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($team, $user),
            self::EDIT => $this->canEdit($team, $user),
            self::DELETE => $this->canDelete($team, $user),
            self::MANAGE_MEMBERS => $this->canManageMembers($team, $user),
            self::MANAGE_PAYMENTS => $this->canManagePayments($team, $user),
            self::MANAGE_DOCUMENTS => $this->canManageDocuments($team, $user),
            self::CREATE_EVENT => $this->canCreateEvent($team, $user),
            default => false,
        };
    }

    private function canView(Team $team, User $user): bool
    {
        // Propriétaire du club
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }

        // Gestionnaire du club
        if ($this->isClubManager($team, $user)) {
            return true;
        }

        // Membre de l'équipe
        return $this->isTeamMember($team, $user);
    }

    private function canEdit(Team $team, User $user): bool
    {
        // Propriétaire du club
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }

        // Gestionnaire du club
        if ($this->isClubManager($team, $user)) {
            return true;
        }

        // Coach de l'équipe
        return $this->isTeamCoach($team, $user);
    }

    private function canDelete(Team $team, User $user): bool
    {
        // Propriétaire du club
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }

        // Gestionnaire du club
        return $this->isClubManager($team, $user);
    }

    private function canManageMembers(Team $team, User $user): bool
    {
        // Propriétaire du club
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }

        // Gestionnaire du club
        if ($this->isClubManager($team, $user)) {
            return true;
        }

        // Coach de l'équipe
        return $this->isTeamCoach($team, $user);
    }

    private function canManagePayments(Team $team, User $user): bool
    {
        // Propriétaire du club
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }

        // Gestionnaire du club
        if ($this->isClubManager($team, $user)) {
            return true;
        }

        // Coach de l'équipe peut voir et envoyer des rappels
        return $this->isTeamCoach($team, $user);
    }

    private function canManageDocuments(Team $team, User $user): bool
    {
        // Propriétaire du club
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }

        // Gestionnaire du club
        if ($this->isClubManager($team, $user)) {
            return true;
        }

        // Coach de l'équipe peut valider les documents
        return $this->isTeamCoach($team, $user);
    }

    private function canCreateEvent(Team $team, User $user): bool
    {
        // Propriétaire du club
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }

        // Gestionnaire du club
        return $this->isClubManager($team, $user);
    }

    private function isClubManager(Team $team, User $user): bool
    {
        $manager = $this->entityManager->getRepository(ClubManager::class)
            ->findOneBy([
                'club' => $team->getClub(),
                'user' => $user
            ]);

        return $manager !== null;
    }

    private function isTeamMember(Team $team, User $user): bool
    {
        $member = $this->entityManager->getRepository(TeamMember::class)
            ->findOneBy([
                'team' => $team,
                'user' => $user,
                'isActive' => true
            ]);

        return $member !== null;
    }

    private function isTeamCoach(Team $team, User $user): bool
    {
        $member = $this->entityManager->getRepository(TeamMember::class)
            ->findOneBy([
                'team' => $team,
                'user' => $user,
                'role' => 'coach',
                'isActive' => true
            ]);

        return $member !== null;
    }
} 