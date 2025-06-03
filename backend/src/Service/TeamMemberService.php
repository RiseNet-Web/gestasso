<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Enum\TeamMemberRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Service pour gérer les membres d'équipe avec validation d'âge
 */
class TeamMemberService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Ajoute un membre à une équipe avec validation d'âge
     */
    public function addMemberToTeam(User $user, Team $team, string $role = 'athlete'): TeamMember
    {
        $roleEnum = $this->normalizeRole($role);
        
        // Vérifier si l'utilisateur respecte les restrictions d'âge (sauf pour les coachs)
        if ($roleEnum === TeamMemberRole::ATHLETE && !$team->userMeetsAgeRestrictions($user, $role)) {
            $errorMessage = $team->getAgeRestrictionErrorMessage($user);
            throw new BadRequestException($errorMessage ?? 'L\'utilisateur ne respecte pas les restrictions d\'âge de l\'équipe.');
        }

        // Vérifier si l'utilisateur est déjà membre de l'équipe
        if ($team->hasMember($user)) {
            throw new BadRequestException('L\'utilisateur est déjà membre de cette équipe.');
        }

        // Vérifier la limite de membres
        if ($team->getMaxMembers() && $team->getMemberCount() >= $team->getMaxMembers()) {
            throw new BadRequestException('L\'équipe a atteint sa limite de membres.');
        }

        // Créer le membre d'équipe
        $teamMember = new TeamMember();
        $teamMember->setUser($user);
        $teamMember->setTeam($team);
        $teamMember->setRole($roleEnum);
        $teamMember->setJoinedAt(new \DateTime());
        $teamMember->setIsActive(true);

        $this->entityManager->persist($teamMember);
        $this->entityManager->flush();

        return $teamMember;
    }

    /**
     * Normalise le rôle en enum TeamMemberRole
     */
    private function normalizeRole(string $role): TeamMemberRole
    {
        return match(strtolower($role)) {
            'athlete', 'player' => TeamMemberRole::ATHLETE,
            'coach', 'trainer' => TeamMemberRole::COACH,
            default => TeamMemberRole::ATHLETE
        };
    }

    /**
     * Valide qu'un utilisateur peut rejoindre une équipe
     */
    public function validateUserCanJoinTeam(User $user, Team $team, string $role = 'athlete'): array
    {
        $errors = [];
        $roleEnum = $this->normalizeRole($role);

        // Vérification de l'âge pour les athlètes
        if ($roleEnum === TeamMemberRole::ATHLETE && !$team->userMeetsAgeRestrictions($user, $role)) {
            $errors[] = $team->getAgeRestrictionErrorMessage($user);
        }

        // Vérification si déjà membre
        if ($team->hasMember($user)) {
            $errors[] = 'L\'utilisateur est déjà membre de cette équipe.';
        }

        // Vérification de la limite de membres
        if ($team->getMaxMembers() && $team->getMemberCount() >= $team->getMaxMembers()) {
            $errors[] = 'L\'équipe a atteint sa limite de membres.';
        }

        return array_filter($errors);
    }

    /**
     * Retire un membre d'une équipe
     */
    public function removeMemberFromTeam(User $user, Team $team): bool
    {
        foreach ($team->getTeamMembers() as $member) {
            if ($member->getUser() === $user && $member->isActive()) {
                $member->setIsActive(false);
                $member->setLeftAt(new \DateTime());
                
                $this->entityManager->persist($member);
                $this->entityManager->flush();
                
                return true;
            }
        }

        return false;
    }
} 