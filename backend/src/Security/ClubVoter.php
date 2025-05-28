<?php

namespace App\Security;

use App\Entity\Club;
use App\Entity\User;
use App\Entity\ClubManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ClubVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';
    public const MANAGE = 'manage';
    public const CREATE_TEAM = 'create_team';
    public const MANAGE_FINANCES = 'manage_finances';

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW, self::EDIT, self::DELETE, self::MANAGE, 
            self::CREATE_TEAM, self::MANAGE_FINANCES
        ]) && $subject instanceof Club;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Club $club */
        $club = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($club, $user),
            self::EDIT => $this->canEdit($club, $user),
            self::DELETE => $this->canDelete($club, $user),
            self::MANAGE => $this->canManage($club, $user),
            self::CREATE_TEAM => $this->canCreateTeam($club, $user),
            self::MANAGE_FINANCES => $this->canManageFinances($club, $user),
            default => false,
        };
    }

    private function canView(Club $club, User $user): bool
    {
        // Les clubs publics sont visibles par tous
        if ($club->isPublic()) {
            return true;
        }

        // Propriétaire du club
        if ($club->getOwner() === $user) {
            return true;
        }

        // Gestionnaire du club
        if ($this->isClubManager($club, $user)) {
            return true;
        }

        // Membre d'une équipe du club
        return $this->isClubMember($club, $user);
    }

    private function canEdit(Club $club, User $user): bool
    {
        // Propriétaire du club
        if ($club->getOwner() === $user) {
            return true;
        }

        // Gestionnaire du club
        return $this->isClubManager($club, $user);
    }

    private function canDelete(Club $club, User $user): bool
    {
        // Seul le propriétaire peut supprimer le club
        return $club->getOwner() === $user;
    }

    private function canManage(Club $club, User $user): bool
    {
        // Propriétaire du club
        if ($club->getOwner() === $user) {
            return true;
        }

        // Gestionnaire du club
        return $this->isClubManager($club, $user);
    }

    private function canCreateTeam(Club $club, User $user): bool
    {
        return $this->canManage($club, $user);
    }

    private function canManageFinances(Club $club, User $user): bool
    {
        return $this->canManage($club, $user);
    }

    private function isClubManager(Club $club, User $user): bool
    {
        $manager = $this->entityManager->getRepository(ClubManager::class)
            ->findOneBy([
                'club' => $club,
                'user' => $user
            ]);

        return $manager !== null;
    }

    private function isClubMember(Club $club, User $user): bool
    {
        // Vérifier si l'utilisateur est membre d'au moins une équipe du club
        $qb = $this->entityManager->createQueryBuilder();
        
        $result = $qb->select('COUNT(tm.id)')
            ->from('App\Entity\TeamMember', 'tm')
            ->join('tm.team', 't')
            ->where('t.club = :club')
            ->andWhere('tm.user = :user')
            ->andWhere('tm.isActive = true')
            ->setParameter('club', $club)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }
} 