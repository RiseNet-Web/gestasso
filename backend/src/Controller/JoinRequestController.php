<?php

namespace App\Controller;

use App\Entity\JoinRequest;
use App\Entity\Team;
use App\Entity\User;
use App\Entity\TeamMember;
use App\Entity\Notification;
use App\Security\TeamVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/join-requests')]
class JoinRequestController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
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

        // Vérifier que le club accepte les demandes
        if (!$team->getClub()->isAllowJoinRequests()) {
            return new JsonResponse(['error' => 'Ce club n\'accepte pas les demandes d\'adhésion'], Response::HTTP_FORBIDDEN);
        }

        // Vérifier qu'il n'y a pas déjà une demande en cours
        $existingRequest = $this->entityManager->getRepository(JoinRequest::class)
            ->findOneBy([
                'user' => $user,
                'team' => $team,
                'status' => 'pending'
            ]);

        if ($existingRequest) {
            return new JsonResponse(['error' => 'Une demande est déjà en cours pour cette équipe'], Response::HTTP_CONFLICT);
        }

        // Vérifier que l'utilisateur n'est pas déjà membre
        $existingMember = $this->entityManager->getRepository(TeamMember::class)
            ->findOneBy([
                'user' => $user,
                'team' => $team,
                'isActive' => true
            ]);

        if ($existingMember) {
            return new JsonResponse(['error' => 'Vous êtes déjà membre de cette équipe'], Response::HTTP_CONFLICT);
        }

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
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($joinRequest);

        // Créer une notification pour les gestionnaires du club
        $this->createJoinRequestNotification($joinRequest);

        $this->entityManager->flush();

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
            'requestedRole' => $joinRequest->getRequestedRole(),
            'status' => $joinRequest->getStatus(),
            'createdAt' => $joinRequest->getCreatedAt()->format('c')
        ], Response::HTTP_CREATED);
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
                'requestedRole' => $request->getRequestedRole(),
                'assignedRole' => $request->getAssignedRole(),
                'status' => $request->getStatus(),
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
                'requestedRole' => $request->getRequestedRole(),
                'assignedRole' => $request->getAssignedRole(),
                'status' => $request->getStatus(),
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
        if ($joinRequest->getStatus() !== 'pending') {
            return new JsonResponse(['error' => 'Cette demande a déjà été traitée'], Response::HTTP_BAD_REQUEST);
        }

        $this->denyAccessUnlessGranted(TeamVoter::MANAGE_MEMBERS, $joinRequest->getTeam());

        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $assignedRole = $data['assignedRole'] ?? $joinRequest->getRequestedRole();
        if (!in_array($assignedRole, ['athlete', 'coach'])) {
            return new JsonResponse(['error' => 'Rôle assigné invalide'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier que l'utilisateur n'est pas déjà membre
        $existingMember = $this->entityManager->getRepository(TeamMember::class)
            ->findOneBy([
                'user' => $joinRequest->getUser(),
                'team' => $joinRequest->getTeam(),
                'isActive' => true
            ]);

        if ($existingMember) {
            return new JsonResponse(['error' => 'Cet utilisateur est déjà membre de l\'équipe'], Response::HTTP_CONFLICT);
        }

        // Approuver la demande
        $joinRequest->setStatus('approved')
                   ->setAssignedRole($assignedRole)
                   ->setReviewedBy($user)
                   ->setReviewedAt(new \DateTime())
                   ->setReviewNotes($data['reviewNotes'] ?? null);

        // Créer le membre d'équipe
        $teamMember = new TeamMember();
        $teamMember->setUser($joinRequest->getUser())
                   ->setTeam($joinRequest->getTeam())
                   ->setRole($assignedRole);

        $this->entityManager->persist($teamMember);

        // Mettre à jour les rôles de l'utilisateur
        $requestUser = $joinRequest->getUser();
        $currentRoles = $requestUser->getRoles();
        
        if ($assignedRole === 'coach' && !in_array('ROLE_COACH', $currentRoles)) {
            $currentRoles[] = 'ROLE_COACH';
            $requestUser->setRoles($currentRoles);
        } elseif ($assignedRole === 'athlete' && !in_array('ROLE_ATHLETE', $currentRoles)) {
            $currentRoles[] = 'ROLE_ATHLETE';
            $requestUser->setRoles($currentRoles);
        }

        // Créer une notification pour l'utilisateur
        $notification = new Notification();
        $notification->setUser($joinRequest->getUser())
                     ->setType('join_request_approved')
                     ->setTitle('Demande d\'adhésion approuvée')
                     ->setMessage("Votre demande d'adhésion à l'équipe {$joinRequest->getTeam()->getName()} a été approuvée.")
                     ->setData([
                         'teamId' => $joinRequest->getTeam()->getId(),
                         'teamName' => $joinRequest->getTeam()->getName(),
                         'assignedRole' => $assignedRole
                     ]);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $joinRequest->getId(),
            'status' => $joinRequest->getStatus(),
            'assignedRole' => $joinRequest->getAssignedRole(),
            'reviewedAt' => $joinRequest->getReviewedAt()->format('c'),
            'teamMember' => [
                'id' => $teamMember->getId(),
                'role' => $teamMember->getRole(),
                'joinedAt' => $teamMember->getJoinedAt()->format('c')
            ]
        ]);
    }

    #[Route('/{id}/reject', name: 'api_join_requests_reject', methods: ['POST'])]
    public function reject(JoinRequest $joinRequest, Request $request): JsonResponse
    {
        if ($joinRequest->getStatus() !== 'pending') {
            return new JsonResponse(['error' => 'Cette demande a déjà été traitée'], Response::HTTP_BAD_REQUEST);
        }

        $this->denyAccessUnlessGranted(TeamVoter::MANAGE_MEMBERS, $joinRequest->getTeam());

        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $joinRequest->setStatus('rejected')
                   ->setReviewedBy($user)
                   ->setReviewedAt(new \DateTime())
                   ->setReviewNotes($data['reviewNotes'] ?? null);

        // Créer une notification pour l'utilisateur
        $notification = new Notification();
        $notification->setUser($joinRequest->getUser())
                     ->setType('join_request_rejected')
                     ->setTitle('Demande d\'adhésion refusée')
                     ->setMessage("Votre demande d'adhésion à l'équipe {$joinRequest->getTeam()->getName()} a été refusée.")
                     ->setData([
                         'teamId' => $joinRequest->getTeam()->getId(),
                         'teamName' => $joinRequest->getTeam()->getName(),
                         'reviewNotes' => $joinRequest->getReviewNotes()
                     ]);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $joinRequest->getId(),
            'status' => $joinRequest->getStatus(),
            'reviewedAt' => $joinRequest->getReviewedAt()->format('c'),
            'reviewNotes' => $joinRequest->getReviewNotes()
        ]);
    }

    private function createJoinRequestNotification(JoinRequest $joinRequest): void
    {
        // Notifier le propriétaire du club
        $owner = $joinRequest->getClub()->getOwner();
        $notification = new Notification();
        $notification->setUser($owner)
                     ->setType('new_join_request')
                     ->setTitle('Nouvelle demande d\'adhésion')
                     ->setMessage("{$joinRequest->getUser()->getFirstName()} {$joinRequest->getUser()->getLastName()} souhaite rejoindre l'équipe {$joinRequest->getTeam()->getName()}")
                     ->setData([
                         'joinRequestId' => $joinRequest->getId(),
                         'teamId' => $joinRequest->getTeam()->getId(),
                         'teamName' => $joinRequest->getTeam()->getName(),
                         'userId' => $joinRequest->getUser()->getId(),
                         'userName' => $joinRequest->getUser()->getFirstName() . ' ' . $joinRequest->getUser()->getLastName()
                     ]);

        $this->entityManager->persist($notification);

        // Notifier les gestionnaires du club
        $managers = $this->entityManager->getRepository('App\Entity\ClubManager')
            ->findBy(['club' => $joinRequest->getClub()]);

        foreach ($managers as $manager) {
            $managerNotification = new Notification();
            $managerNotification->setUser($manager->getUser())
                               ->setType('new_join_request')
                               ->setTitle('Nouvelle demande d\'adhésion')
                               ->setMessage("{$joinRequest->getUser()->getFirstName()} {$joinRequest->getUser()->getLastName()} souhaite rejoindre l'équipe {$joinRequest->getTeam()->getName()}")
                               ->setData([
                                   'joinRequestId' => $joinRequest->getId(),
                                   'teamId' => $joinRequest->getTeam()->getId(),
                                   'teamName' => $joinRequest->getTeam()->getName(),
                                   'userId' => $joinRequest->getUser()->getId(),
                                   'userName' => $joinRequest->getUser()->getFirstName() . ' ' . $joinRequest->getUser()->getLastName()
                               ]);

            $this->entityManager->persist($managerNotification);
        }
    }
} 