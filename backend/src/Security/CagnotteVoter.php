<?php

namespace App\Security;

use App\Entity\Cagnotte;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CagnotteVoter extends Voter
{
    public const VIEW = 'CAGNOTTE_VIEW';
    public const MANAGE = 'CAGNOTTE_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MANAGE])
            && $subject instanceof Cagnotte;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Cagnotte $cagnotte */
        $cagnotte = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($cagnotte, $user),
            self::MANAGE => $this->canManage($cagnotte, $user),
            default => false,
        };
    }

    private function canView(Cagnotte $cagnotte, User $user): bool
    {
        // Le propriétaire de la cagnotte peut toujours la voir
        if ($cagnotte->getUser() === $user) {
            return true;
        }

        $team = $cagnotte->getTeam();
        
        // Les managers du club peuvent voir toutes les cagnottes
        foreach ($user->getClubManagers() as $clubManager) {
            if ($clubManager->getClub() === $team->getClub()) {
                return true;
            }
        }

        // Les coachs peuvent voir les cagnottes de leurs athlètes
        foreach ($user->getTeamMemberships() as $membership) {
            if ($membership->getTeam() === $team && 
                $membership->getRole() === 'coach' && 
                $membership->isActive()) {
                return true;
            }
        }

        // Le propriétaire du club peut voir toutes les cagnottes
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }

        return false;
    }

    private function canManage(Cagnotte $cagnotte, User $user): bool
    {
        $team = $cagnotte->getTeam();
        
        // Seuls les managers du club peuvent gérer les cagnottes
        foreach ($user->getClubManagers() as $clubManager) {
            if ($clubManager->getClub() === $team->getClub()) {
                return true;
            }
        }

        // Le propriétaire du club peut gérer les cagnottes
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }

        return false;
    }
} 