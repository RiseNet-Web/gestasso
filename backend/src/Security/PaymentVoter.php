<?php

namespace App\Security;

use App\Entity\Payment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PaymentVoter extends Voter
{
    public const VIEW = 'PAYMENT_VIEW';
    public const UPDATE = 'PAYMENT_UPDATE';
    public const SEND_REMINDER = 'PAYMENT_SEND_REMINDER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::UPDATE, self::SEND_REMINDER])
            && $subject instanceof Payment;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Payment $payment */
        $payment = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($payment, $user),
            self::UPDATE => $this->canUpdate($payment, $user),
            self::SEND_REMINDER => $this->canSendReminder($payment, $user),
            default => false,
        };
    }

    private function canView(Payment $payment, User $user): bool
    {
        // Le propriétaire du paiement peut toujours le voir
        if ($payment->getUser() === $user) {
            return true;
        }

        $team = $payment->getTeam();
        
        // Les managers du club peuvent voir tous les paiements
        foreach ($user->getClubManagers() as $clubManager) {
            if ($clubManager->getClub() === $team->getClub()) {
                return true;
            }
        }

        // Les coachs peuvent voir les paiements de leurs athlètes
        foreach ($user->getTeamMemberships() as $membership) {
            if ($membership->getTeam() === $team && 
                $membership->getRole() === 'coach' && 
                $membership->isActive()) {
                return true;
            }
        }

        // Le propriétaire du club peut voir tous les paiements
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }

        return false;
    }

    private function canUpdate(Payment $payment, User $user): bool
    {
        $team = $payment->getTeam();
        
        // Seuls les managers du club peuvent mettre à jour les paiements
        foreach ($user->getClubManagers() as $clubManager) {
            if ($clubManager->getClub() === $team->getClub()) {
                return true;
            }
        }

        // Le propriétaire du club peut mettre à jour les paiements
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }

        return false;
    }

    private function canSendReminder(Payment $payment, User $user): bool
    {
        // Seuls les paiements en attente peuvent recevoir des rappels
        if (!in_array($payment->getStatus(), ['pending', 'overdue'])) {
            return false;
        }

        $team = $payment->getTeam();
        
        // Les managers du club peuvent envoyer des rappels
        foreach ($user->getClubManagers() as $clubManager) {
            if ($clubManager->getClub() === $team->getClub()) {
                return true;
            }
        }

        // Les coachs peuvent envoyer des rappels à leurs athlètes
        foreach ($user->getTeamMemberships() as $membership) {
            if ($membership->getTeam() === $team && 
                $membership->getRole() === 'coach' && 
                $membership->isActive()) {
                return true;
            }
        }

        // Le propriétaire du club peut envoyer des rappels
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }

        return false;
    }
} 