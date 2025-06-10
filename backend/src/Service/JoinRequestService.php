<?php

namespace App\Service;

use App\Entity\JoinRequest;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\JoinRequestStatus;
use App\Enum\TeamMemberRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class JoinRequestService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {}

    public function createJoinRequest(User $user, Team $team, array $requestData): JoinRequest
    {
        // Vérifier que le club accepte les demandes d'adhésion
        if (!$team->getClub()->allowJoinRequests()) {
            throw new \InvalidArgumentException('Ce club n\'accepte pas les demandes d\'adhésion');
        }

        // Vérifier qu'il n'y a pas déjà une demande en cours
        $joinRequestRepo = $this->entityManager->getRepository(JoinRequest::class);
        if ($joinRequestRepo->hasPendingRequestForTeam($user, $team)) {
            throw new \InvalidArgumentException('Une demande est déjà en cours pour cette équipe');
        }

        // Vérifier que l'utilisateur n'est pas déjà membre
        $teamMemberRepo = $this->entityManager->getRepository(TeamMember::class);
        $existingMember = $teamMemberRepo->findOneBy([
            'user' => $user,
            'team' => $team,
            'isActive' => true
        ]);

        if ($existingMember) {
            throw new \InvalidArgumentException('Vous êtes déjà membre de cette équipe');
        }

        // Créer la demande
        $joinRequest = new JoinRequest();
        $joinRequest->setUser($user)
                   ->setTeam($team)
                   ->setClub($team->getClub())
                   ->setMessage($requestData['message'] ?? null)
                   ->setRequestedRole($requestData['requestedRole'] ?? TeamMemberRole::ATHLETE)
                   ->setStatus(JoinRequestStatus::PENDING);

        // Validation
        $errors = $this->validator->validate($joinRequest);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            throw new \InvalidArgumentException(implode(', ', $errorMessages));
        }

        $this->entityManager->persist($joinRequest);
        $this->entityManager->flush();

        return $joinRequest;
    }

    public function approveJoinRequest(JoinRequest $joinRequest, User $reviewer, TeamMemberRole $assignedRole, ?string $notes = null): array
    {
        // Vérifier que l'utilisateur n'est pas déjà membre
        $teamMemberRepo = $this->entityManager->getRepository(TeamMember::class);
        $existingMember = $teamMemberRepo->findOneBy([
            'user' => $joinRequest->getUser(),
            'team' => $joinRequest->getTeam(),
            'isActive' => true
        ]);

        if ($existingMember) {
            throw new \InvalidArgumentException('Cet utilisateur est déjà membre de l\'équipe');
        }

        // Approuver la demande
        $joinRequest->setStatus(JoinRequestStatus::APPROVED)
                   ->setAssignedRole($assignedRole)
                   ->setReviewedBy($reviewer)
                   ->setReviewedAt(new \DateTime())
                   ->setReviewNotes($notes);

        // Créer le membre d'équipe
        $teamMember = new TeamMember();
        $teamMember->setUser($joinRequest->getUser())
                   ->setTeam($joinRequest->getTeam())
                   ->setRole($assignedRole)
                   ->setIsActive(true);

        $this->entityManager->persist($teamMember);
        $this->entityManager->flush();

        return [
            'joinRequest' => $joinRequest,
            'teamMember' => $teamMember
        ];
    }

    public function rejectJoinRequest(JoinRequest $joinRequest, User $reviewer, string $notes): JoinRequest
    {
        $joinRequest->setStatus(JoinRequestStatus::REJECTED)
                   ->setReviewedBy($reviewer)
                   ->setReviewedAt(new \DateTime())
                   ->setReviewNotes($notes)
                   ->setAssignedRole(null);

        $this->entityManager->flush();

        return $joinRequest;
    }

    public function cancelJoinRequest(JoinRequest $joinRequest, User $user): void
    {
        // Vérifier que l'utilisateur peut annuler cette demande
        if ($joinRequest->getUser() !== $user) {
            throw new \InvalidArgumentException('Vous ne pouvez annuler que vos propres demandes');
        }

        // Vérifier que la demande est encore en attente
        if ($joinRequest->getStatus() !== JoinRequestStatus::PENDING) {
            throw new \InvalidArgumentException('Seules les demandes en attente peuvent être annulées');
        }

        $this->entityManager->remove($joinRequest);
        $this->entityManager->flush();
    }
} 