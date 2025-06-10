<?php

namespace App\Security;

use App\Entity\Document;
use App\Entity\User;
use App\Enum\TeamMemberRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DocumentVoter extends Voter
{
    public const VIEW = 'DOCUMENT_VIEW';
    public const UPLOAD = 'DOCUMENT_UPLOAD';
    public const VALIDATE = 'DOCUMENT_VALIDATE';
    public const DELETE = 'DOCUMENT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::UPLOAD, self::VALIDATE, self::DELETE])
            && $subject instanceof Document;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Document $document */
        $document = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($document, $user),
            self::UPLOAD => $this->canUpload($document, $user),
            self::VALIDATE => $this->canValidate($document, $user),
            self::DELETE => $this->canDelete($document, $user),
            default => false,
        };
    }

    private function canView(Document $document, User $user): bool
    {
        // Le propriétaire du document peut toujours le voir
        if ($document->getUser() === $user) {
            return true;
        }

        $team = $document->getDocumentTypeEntity()->getTeam();
        
        // Le propriétaire du club peut voir tous les documents
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }
        
        // Les managers du club peuvent voir tous les documents
        foreach ($user->getClubManagers() as $clubManager) {
            if ($clubManager->getClub() === $team->getClub()) {
                return true;
            }
        }

        // Les coachs peuvent voir les documents de leurs athlètes
        foreach ($user->getTeamMemberships() as $membership) {
            if ($membership->getTeam() === $team && 
                $membership->getRole() === TeamMemberRole::COACH && 
                $membership->isActive()) {
                return true;
            }
        }

        return false;
    }

    private function canUpload(Document $document, User $user): bool
    {
        // Seul le propriétaire peut uploader ses documents
        return $document->getUser() === $user;
    }

    private function canValidate(Document $document, User $user): bool
    {
        $team = $document->getDocumentTypeEntity()->getTeam();
        
        // Les managers du club peuvent valider
        foreach ($user->getClubManagers() as $clubManager) {
            if ($clubManager->getClub() === $team->getClub()) {
                return true;
            }
        }

        // Le propriétaire du club peut valider
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }

        // Les coachs peuvent valider les documents de leur équipe
        foreach ($user->getTeamMemberships() as $membership) {
            if ($membership->getTeam() === $team && 
                $membership->getRole() === TeamMemberRole::COACH && 
                $membership->isActive()) {
                return true;
            }
        }

        return false;
    }

    private function canDelete(Document $document, User $user): bool
    {
        // Le propriétaire peut supprimer son document s'il n'est pas encore approuvé
        if ($document->getUser() === $user && $document->getStatus() !== \App\Enum\DocumentStatus::APPROVED) {
            return true;
        }

        $team = $document->getDocumentTypeEntity()->getTeam();
        
        // Le propriétaire du club peut supprimer
        if ($team->getClub()->getOwner() === $user) {
            return true;
        }
        
        // Les managers du club peuvent supprimer
        foreach ($user->getClubManagers() as $clubManager) {
            if ($clubManager->getClub() === $team->getClub()) {
                return true;
            }
        }

        return false;
    }
} 0