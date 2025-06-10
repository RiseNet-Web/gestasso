<?php

namespace App\Controller;

use App\Entity\JoinRequest;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\JoinRequestStatus;
use App\Enum\TeamMemberRole;
use App\Security\TeamVoter;
use App\Service\JoinRequestService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/join-requests')]
class JoinRequestController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JoinRequestService $joinRequestService,
        private NotificationService $notificationService
    ) {}

    #[Route('', name: 'api_join_requests_create', methods: ['POST'])]
    #[IsGranted('ROLE_MEMBER')]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['teamId'])) {
            return new JsonResponse(['error' => 'ID de l\'équipe requis'], Response::HTTP_BAD_REQUEST);
        }

        $team = $this->entityManager->getRepository(Team::class)->find($data['teamId']);
        if (!$team || !$team->isActive()) {
            return new JsonResponse(['error' => 'Équipe non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Convertir les données pour le service
        $requestData = [
            'message' => $data['message'] ?? null,
            'requestedRole' => isset($data['requestedRole']) ? TeamMemberRole::from($data['requestedRole']) : TeamMemberRole::ATHLETE
        ];

        try {
            // Utiliser le service pour créer la demande
            $joinRequest = $this->joinRequestService->createJoinRequest($user, $team, $requestData);

            // Créer les notifications via le service
            $this->notificationService->notifyJoinRequest($joinRequest);

            // Marquer l'onboarding comme terminé si c'était un member
            if ($user->getOnboardingType() === 'member' && !$user->isOnboardingCompleted()) {
                $user->setOnboardingCompleted(true);
                $this->entityManager->flush();
            }

            return new JsonResponse([
                'id' => $joinRequest->getId(),
                'team' => [
                    'id' => $team->getId(),
                    'name' => $team->getName(),
                    'club' => [
                        'id' => $team->getClub()->getId(),
                        'name' => $team->getClub()->getName()
                    ]
                ],
                'message' => $joinRequest->getMessage(),
                'requestedRole' => $joinRequest->getRequestedRole()->value,
                'status' => $joinRequest->getStatus()->value,
                'createdAt' => $joinRequest->getCreatedAt()->format('c')
            ], Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/my-requests', name: 'api_join_requests_my', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myRequests(): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $requests = $this->entityManager->getRepository(JoinRequest::class)
            ->findBy(['user' => $user], ['createdAt' => 'DESC']);

        $requestsData = [];
        foreach ($requests as $request) {
            $requestsData[] = [
                'id' => $request->getId(),
                'team' => [
                    'id' => $request->getTeam()->getId(),
                    'name' => $request->getTeam()->getName(),
                    'club' => [
                        'id' => $request->getClub()->getId(),
                        'name' => $request->getClub()->getName()
                    ]
                ],
                'message' => $request->getMessage(),
                'requestedRole' => $request->getRequestedRole()?->value,
                'assignedRole' => $request->getAssignedRole()?->value,
                'status' => $request->getStatus()->value,
                'reviewNotes' => $request->getReviewNotes(),
                'reviewedAt' => $request->getReviewedAt()?->format('c'),
                'createdAt' => $request->getCreatedAt()->format('c')
            ];
        }

        return new JsonResponse($requestsData);
    }

    #[Route('/team/{teamId}', name: 'api_join_requests_team', methods: ['GET'])]
    public function teamRequests(int $teamId): JsonResponse
    {
        $team = $this->entityManager->getRepository(Team::class)->find($teamId);
        if (!$team) {
            return new JsonResponse(['error' => 'Équipe non trouvée'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(TeamVoter::MANAGE_MEMBERS, $team);

        $requests = $this->entityManager->getRepository(JoinRequest::class)
            ->findBy(['team' => $team], ['createdAt' => 'DESC']);

        $requestsData = [];
        foreach ($requests as $request) {
            $requestsData[] = [
                'id' => $request->getId(),
                'user' => [
                    'id' => $request->getUser()->getId(),
                    'firstName' => $request->getUser()->getFirstName(),
                    'lastName' => $request->getUser()->getLastName(),
                    'email' => $request->getUser()->getEmail()
                ],
                'message' => $request->getMessage(),
                'requestedRole' => $request->getRequestedRole()?->value,
                'assignedRole' => $request->getAssignedRole()?->value,
                'status' => $request->getStatus()->value,
                'reviewNotes' => $request->getReviewNotes(),
                'reviewedBy' => $request->getReviewedBy() ? [
                    'id' => $request->getReviewedBy()->getId(),
                    'firstName' => $request->getReviewedBy()->getFirstName(),
                    'lastName' => $request->getReviewedBy()->getLastName()
                ] : null,
                'reviewedAt' => $request->getReviewedAt()?->format('c'),
                'createdAt' => $request->getCreatedAt()->format('c')
            ];
        }

        return new JsonResponse($requestsData);
    }

    #[Route('/{id}/approve', name: 'api_join_requests_approve', methods: ['POST'])]
    public function approve(JoinRequest $joinRequest, Request $request): JsonResponse
    {
        if ($joinRequest->getStatus() !== JoinRequestStatus::PENDING) {
            return new JsonResponse(['error' => 'Cette demande a déjà été traitée'], Response::HTTP_BAD_REQUEST);
        }

        $this->denyAccessUnlessGranted(TeamVoter::MANAGE_MEMBERS, $joinRequest->getTeam());

        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        // Valider le rôle assigné
        $assignedRoleValue = $data['assignedRole'] ?? $joinRequest->getRequestedRole()?->value ?? 'athlete';
        try {
            $assignedRole = TeamMemberRole::from($assignedRoleValue);
        } catch (\ValueError $e) {
            return new JsonResponse(['error' => 'Rôle assigné invalide'], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Utiliser le service pour approuver la demande
            $result = $this->joinRequestService->approveJoinRequest(
                $joinRequest, 
                $user, 
                $assignedRole, 
                $data['reviewNotes'] ?? null
            );

            $teamMember = $result['teamMember'];

            // Mettre à jour les rôles de l'utilisateur
            $requestUser = $joinRequest->getUser();
            $currentRoles = $requestUser->getRoles();
            
            if ($assignedRole === TeamMemberRole::COACH && !in_array('ROLE_COACH', $currentRoles)) {
                $currentRoles[] = 'ROLE_COACH';
                $requestUser->setRoles($currentRoles);
            } elseif ($assignedRole === TeamMemberRole::ATHLETE && !in_array('ROLE_ATHLETE', $currentRoles)) {
                $currentRoles[] = 'ROLE_ATHLETE';
                $requestUser->setRoles($currentRoles);
            }

            // Créer une notification via le service
            $this->notificationService->notifyJoinRequestApproval($joinRequest, $assignedRole);

            return new JsonResponse([
                'id' => $joinRequest->getId(),
                'status' => $joinRequest->getStatus()->value,
                'assignedRole' => $joinRequest->getAssignedRole()->value,
                'reviewedAt' => $joinRequest->getReviewedAt()->format('c'),
                'teamMember' => [
                    'id' => $teamMember->getId(),
                    'role' => $teamMember->getRole()->value,
                    'joinedAt' => $teamMember->getJoinedAt()->format('c')
                ]
            ]);

        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/reject', name: 'api_join_requests_reject', methods: ['POST'])]
    public function reject(JoinRequest $joinRequest, Request $request): JsonResponse
    {
        if ($joinRequest->getStatus() !== JoinRequestStatus::PENDING) {
            return new JsonResponse(['error' => 'Cette demande a déjà été traitée'], Response::HTTP_BAD_REQUEST);
        }

        $this->denyAccessUnlessGranted(TeamVoter::MANAGE_MEMBERS, $joinRequest->getTeam());

        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        try {
            // Utiliser le service pour rejeter la demande
            $this->joinRequestService->rejectJoinRequest(
                $joinRequest, 
                $user, 
                $data['reviewNotes'] ?? 'Aucune raison fournie'
            );

            // Créer une notification via le service
            $this->notificationService->notifyJoinRequestRejection($joinRequest);

            return new JsonResponse([
                'id' => $joinRequest->getId(),
                'status' => $joinRequest->getStatus()->value,
                'reviewedAt' => $joinRequest->getReviewedAt()->format('c'),
                'reviewNotes' => $joinRequest->getReviewNotes()
            ]);

        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/cancel', name: 'api_join_requests_cancel', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(JoinRequest $joinRequest): JsonResponse
    {
        $user = $this->getUser();

        try {
            // Utiliser le service pour annuler la demande
            $this->joinRequestService->cancelJoinRequest($joinRequest, $user);

            return new JsonResponse(['message' => 'Demande annulée avec succès']);

        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
} 