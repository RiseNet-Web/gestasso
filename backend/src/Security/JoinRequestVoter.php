<?php

namespace App\Security;

use App\Entity\JoinRequest;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class JoinRequestVoter extends Voter
{
    public const VIEW = 'JOIN_REQUEST_VIEW';
    public const CREATE = 'JOIN_REQUEST_CREATE';
    public const APPROVE = 'JOIN_REQUEST_APPROVE';
    public const REJECT = 'JOIN_REQUEST_REJECT';
    public const CANCEL = 'JOIN_REQUEST_CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CREATE, self::APPROVE, self::REJECT, self::CANCEL])
            && $subject instanceof JoinRequest;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var JoinRequest $joinRequest */
        $joinRequest = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($joinRequest, $user),
            self::CREATE => $this->canCreate($joinRequest, $user),
            self::APPROVE, self::REJECT => $this->canReview($joinRequest, $user),
            self::CANCEL => $this->canCancel($joinRequest, $user),
            default => false,
        };
    }

    private function canView(JoinRequest $joinRequest, User $user): bool
    {
        // Le demandeur peut voir sa propre demande
        if ($joinRequest->getUser() === $user) {
            return true;
        }

        $club = $joinRequest->getClub();
        
        // Les managers du club peuvent voir toutes les demandes
        foreach ($user->getClubManagers() as $clubManager) {
            if ($clubManager->getClub() === $club) {
                return true;
            }
        }

        // Le propriétaire du club peut voir toutes les demandes
        if ($club->getOwner() === $user) {
            return true;
        }

        // Les coachs peuvent voir les demandes pour leurs équipes
        $team = $joinRequest->getTeam();
        foreach ($user->getTeamMemberships() as $membership) {
            if ($membership->getTeam() === $team && 
                $membership->getRole() === 'coach' && 
                $membership->isActive()) {
                return true;
            }
        }

        return false;
    }

    private function canCreate(JoinRequest $joinRequest, User $user): bool
    {
        // L'utilisateur doit être le demandeur
        if ($joinRequest->getUser() !== $user) {
            return false;
        }

        // L'utilisateur ne doit pas déjà être membre de l'équipe
        $team = $joinRequest->getTeam();
        foreach ($user->getTeamMemberships() as $membership) {
            if ($membership->getTeam() === $team && $membership->isActive()) {
                return false;
            }
        }

        // Le club doit accepter les demandes d'adhésion
        return $joinRequest->getClub()->isAllowJoinRequests();
    }

    private function canReview(JoinRequest $joinRequest, User $user): bool
    {
        // La demande doit être en attente
        if ($joinRequest->getStatus() !== 'pending') {
            return false;
        }

        $club = $joinRequest->getClub();
        
        // Les managers du club peuvent approuver/rejeter
        foreach ($user->getClubManagers() as $clubManager) {
            if ($clubManager->getClub() === $club) {
                return true;
            }
        }

        // Le propriétaire du club peut approuver/rejeter
        if ($club->getOwner() === $user) {
            return true;
        }

        // Les coachs peuvent approuver/rejeter pour leurs équipes
        $team = $joinRequest->getTeam();
        foreach ($user->getTeamMemberships() as $membership) {
            if ($membership->getTeam() === $team && 
                $membership->getRole() === 'coach' && 
                $membership->isActive()) {
                return true;
            }
        }

        return false;
    }

    private function canCancel(JoinRequest $joinRequest, User $user): bool
    {
        // Seul le demandeur peut annuler sa demande
        // Et seulement si elle est encore en attente
        return $joinRequest->getUser() === $user && 
               $joinRequest->getStatus() === 'pending';
    }
} 