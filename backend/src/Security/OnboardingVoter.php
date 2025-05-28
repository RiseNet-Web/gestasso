<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class OnboardingVoter extends Voter
{
    public const ACCESS_CLUB_FEATURES = 'ONBOARDING_ACCESS_CLUB_FEATURES';
    public const ACCESS_MEMBER_FEATURES = 'ONBOARDING_ACCESS_MEMBER_FEATURES';
    public const COMPLETE_ONBOARDING = 'ONBOARDING_COMPLETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::ACCESS_CLUB_FEATURES,
            self::ACCESS_MEMBER_FEATURES,
            self::COMPLETE_ONBOARDING
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::ACCESS_CLUB_FEATURES => $this->canAccessClubFeatures($user),
            self::ACCESS_MEMBER_FEATURES => $this->canAccessMemberFeatures($user),
            self::COMPLETE_ONBOARDING => $this->canCompleteOnboarding($user),
            default => false,
        };
    }

    private function canAccessClubFeatures(User $user): bool
    {
        // L'utilisateur doit avoir complété l'onboarding en tant que owner
        return $user->isOnboardingCompleted() && 
               $user->getOnboardingType() === 'owner';
    }

    private function canAccessMemberFeatures(User $user): bool
    {
        // L'utilisateur doit avoir complété l'onboarding
        return $user->isOnboardingCompleted();
    }

    private function canCompleteOnboarding(User $user): bool
    {
        // L'utilisateur ne doit pas avoir déjà complété l'onboarding
        // et doit avoir choisi un type d'onboarding
        return !$user->isOnboardingCompleted() && 
               $user->getOnboardingType() !== null;
    }
} 