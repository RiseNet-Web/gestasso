<?php

namespace App\Service;

use App\Entity\JoinRequest;
use App\Entity\Team;
use App\Entity\User;
use App\Entity\TeamMember;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class JoinRequestService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private NotificationService $notificationService
    ) {}

    public function createJoinRequest(User $user, Team $team, array $data): JoinRequest
    {
        $joinRequest = new JoinRequest();
        $joinRequest->setUser($user)
                   ->setTeam($team)
                   ->setClub($team->getClub())
                   ->setMessage($data['message'] ?? null)
                   ->setRequestedRole($data['requestedRole'] ?? 'athlete')
                   ->setStatus('pending');
        $errors = $this->validator->validate($joinRequest);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            throw new \InvalidArgumentException(json_encode($errorMessages));
        }
        $this->entityManager->persist($joinRequest);
        $this->entityManager->flush();
        $this->notificationService->notifyJoinRequest($joinRequest);
        return $joinRequest;
    }

    public function approveJoinRequest(JoinRequest $joinRequest, User $reviewer, ?string $assignedRole = null, ?string $reviewNotes = null): void
    {
        $joinRequest->setStatus('approved');
        $joinRequest->setReviewedBy($reviewer);
        $joinRequest->setReviewedAt(new \DateTime());
        $joinRequest->setAssignedRole($assignedRole ?? $joinRequest->getRequestedRole());
        $joinRequest->setReviewNotes($reviewNotes);
        // Ajouter le membre à l'équipe
        $member = new TeamMember();
        $member->setTeam($joinRequest->getTeam());
        $member->setUser($joinRequest->getUser());
        $member->setRole($joinRequest->getAssignedRole());
        $member->setActive(true);
        $this->entityManager->persist($member);
        $this->entityManager->flush();
        // Notifier l'utilisateur
        $this->notificationService->createNotification(
            $joinRequest->getUser(),
            'join_request_approved',
            'Demande d\'adhésion acceptée',
            sprintf('Votre demande pour rejoindre l\'équipe %s a été acceptée.', $joinRequest->getTeam()->getName()),
            [
                'joinRequestId' => $joinRequest->getId(),
                'teamId' => $joinRequest->getTeam()->getId()
            ]
        );
    }

    public function rejectJoinRequest(JoinRequest $joinRequest, User $reviewer, ?string $reviewNotes = null): void
    {
        $joinRequest->setStatus('rejected');
        $joinRequest->setReviewedBy($reviewer);
        $joinRequest->setReviewedAt(new \DateTime());
        $joinRequest->setReviewNotes($reviewNotes);
        $this->entityManager->flush();
        // Notifier l'utilisateur
        $this->notificationService->createNotification(
            $joinRequest->getUser(),
            'join_request_rejected',
            'Demande d\'adhésion refusée',
            sprintf('Votre demande pour rejoindre l\'équipe %s a été refusée.', $joinRequest->getTeam()->getName()),
            [
                'joinRequestId' => $joinRequest->getId(),
                'teamId' => $joinRequest->getTeam()->getId()
            ]
        );
    }
} 