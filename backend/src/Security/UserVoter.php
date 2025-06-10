<?php

namespace App\Security;

use App\Entity\User;
use App\Entity\ClubManager;
use App\Entity\TeamMember;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    public const VIEW_DOCUMENTS = 'VIEW_USER_DOCUMENTS';
    public const VIEW_PROFILE = 'VIEW_USER_PROFILE';

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW_DOCUMENTS, self::VIEW_PROFILE])
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();
        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        return match ($attribute) {
            self::VIEW_DOCUMENTS => $this->canViewUserDocuments($targetUser, $currentUser),
            self::VIEW_PROFILE => $this->canViewUserProfile($targetUser, $currentUser),
            default => false,
        };
    }

    private function canViewUserDocuments(User $targetUser, User $currentUser): bool
    {
        // Ses propres documents
        if ($targetUser->getId() === $currentUser->getId()) {
            return true;
        }

        // Admin global
        if (in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            return true;
        }

        // Gestionnaire d'un club où l'utilisateur target est membre
        return $this->isManagerOfUserClubs($targetUser, $currentUser);
    }

    private function canViewUserProfile(User $targetUser, User $currentUser): bool
    {
        // Son propre profil
        if ($targetUser->getId() === $currentUser->getId()) {
            return true;
        }

        // Admin global
        if (in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            return true;
        }

        // Gestionnaire ou coach d'un club/équipe où l'utilisateur target est membre
        return $this->isManagerOfUserClubs($targetUser, $currentUser) || 
               $this->isCoachOfUser($targetUser, $currentUser);
    }

    /**
     * Vérifie si currentUser est gestionnaire/propriétaire d'un club où targetUser est membre
     */
    private function isManagerOfUserClubs(User $targetUser, User $currentUser): bool
    {
        // Récupérer tous les clubs où targetUser est membre
        $qb = $this->entityManager->createQueryBuilder();
        $targetUserClubIds = $qb->select('DISTINCT c.id')
            ->from('App\Entity\TeamMember', 'tm')
            ->join('tm.team', 't')
            ->join('t.club', 'c')
            ->where('tm.user = :targetUser')
            ->andWhere('tm.isActive = true')
            ->setParameter('targetUser', $targetUser)
            ->getQuery()
            ->getArrayResult();

        if (empty($targetUserClubIds)) {
            return false;
        }

        $clubIds = array_column($targetUserClubIds, 'id');

        // Vérifier si currentUser est propriétaire d'un de ces clubs
        $qb2 = $this->entityManager->createQueryBuilder();
        $ownerClubCount = $qb2->select('COUNT(c.id)')
            ->from('App\Entity\Club', 'c')
            ->where('c.owner = :currentUser')
            ->andWhere($qb2->expr()->in('c.id', ':clubIds'))
            ->setParameter('currentUser', $currentUser)
            ->setParameter('clubIds', $clubIds)
            ->getQuery()
            ->getSingleScalarResult();

        if ($ownerClubCount > 0) {
            return true;
        }

        // Vérifier si currentUser est gestionnaire d'un de ces clubs
        $qb3 = $this->entityManager->createQueryBuilder();
        $managerClubCount = $qb3->select('COUNT(cm.id)')
            ->from('App\Entity\ClubManager', 'cm')
            ->where('cm.user = :currentUser')
            ->andWhere($qb3->expr()->in('cm.club', ':clubIds'))
            ->setParameter('currentUser', $currentUser)
            ->setParameter('clubIds', $clubIds)
            ->getQuery()
            ->getSingleScalarResult();

        return $managerClubCount > 0;
    }

    /**
     * Vérifie si currentUser est coach d'une équipe où targetUser est membre
     */
    private function isCoachOfUser(User $targetUser, User $currentUser): bool
    {
        // Récupérer toutes les équipes où targetUser est membre
        $qb = $this->entityManager->createQueryBuilder();
        $targetUserTeamIds = $qb->select('DISTINCT t.id')
            ->from('App\Entity\TeamMember', 'tm')
            ->join('tm.team', 't')
            ->where('tm.user = :targetUser')
            ->andWhere('tm.isActive = true')
            ->setParameter('targetUser', $targetUser)
            ->getQuery()
            ->getArrayResult();

        if (empty($targetUserTeamIds)) {
            return false;
        }

        $teamIds = array_column($targetUserTeamIds, 'id');

        // Vérifier si currentUser est coach d'une de ces équipes
        $qb2 = $this->entityManager->createQueryBuilder();
        $coachTeamCount = $qb2->select('COUNT(tm.id)')
            ->from('App\Entity\TeamMember', 'tm')
            ->where('tm.user = :currentUser')
            ->andWhere('tm.role = :coachRole')
            ->andWhere('tm.isActive = true')
            ->andWhere($qb2->expr()->in('tm.team', ':teamIds'))
            ->setParameter('currentUser', $currentUser)
            ->setParameter('coachRole', 'coach')
            ->setParameter('teamIds', $teamIds)
            ->getQuery()
            ->getSingleScalarResult();

        return $coachTeamCount > 0;
    }
} 