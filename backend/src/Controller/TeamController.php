<?php

namespace App\Controller;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\Club;
use App\Entity\Season;
use App\Entity\User;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class TeamController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private TeamRepository $teamRepository,
        private UserRepository $userRepository
    ) {}

    #[Route('/clubs/{clubId}/teams', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getClubTeams(int $clubId): JsonResponse
    {
        $club = $this->entityManager->getRepository(Club::class)->find($clubId);
        
        if (!$club) {
            return $this->json(['error' => 'Club non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('CLUB_VIEW', $club);

        $teams = $this->teamRepository->findBy(['club' => $club, 'isActive' => true]);

        return $this->json([
            'teams' => array_map(function (Team $team) {
                return [
                    'id' => $team->getId(),
                    'name' => $team->getName(),
                    'description' => $team->getDescription(),
                    'imagePath' => $team->getImagePath(),
                    'annualPrice' => $team->getAnnualPrice(),
                    'season' => [
                        'id' => $team->getSeason()->getId(),
                        'name' => $team->getSeason()->getName(),
                        'startDate' => $team->getSeason()->getStartDate()->format('Y-m-d'),
                        'endDate' => $team->getSeason()->getEndDate()->format('Y-m-d'),
                        'isActive' => $team->getSeason()->isActive()
                    ],
                    'membersCount' => $team->getTeamMembers()->filter(
                        fn($m) => $m->isActive()
                    )->count(),
                    'createdAt' => $team->getCreatedAt()->format('c')
                ];
            }, $teams)
        ]);
    }

    #[Route('/clubs/{clubId}/teams', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createTeam(int $clubId, Request $request): JsonResponse
    {
        $club = $this->entityManager->getRepository(Club::class)->find($clubId);
        
        if (!$club) {
            return $this->json(['error' => 'Club non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('CLUB_MANAGE', $club);

        $data = json_decode($request->getContent(), true);

        $team = new Team();
        $team->setName($data['name'] ?? '');
        $team->setDescription($data['description'] ?? null);
        $team->setClub($club);
        $team->setAnnualPrice($data['annualPrice'] ?? 0);

        // Récupérer la saison
        if (isset($data['seasonId'])) {
            $season = $this->entityManager->getRepository(Season::class)->find($data['seasonId']);
            if (!$season || $season->getClub() !== $club) {
                return $this->json(['error' => 'Saison invalide'], Response::HTTP_BAD_REQUEST);
            }
            $team->setSeason($season);
        }

        // Valider l'entité
        $errors = $this->validator->validate($team);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($team);
        $this->entityManager->flush();

        return $this->json([
            'id' => $team->getId(),
            'name' => $team->getName(),
            'description' => $team->getDescription(),
            'annualPrice' => $team->getAnnualPrice(),
            'clubId' => $team->getClub()->getId(),
            'seasonId' => $team->getSeason()->getId(),
            'createdAt' => $team->getCreatedAt()->format('c')
        ], Response::HTTP_CREATED);
    }

    #[Route('/teams/{id}', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getTeam(int $id): JsonResponse
    {
        $team = $this->teamRepository->find($id);
        
        if (!$team) {
            return $this->json(['error' => 'Équipe non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('TEAM_VIEW', $team);

        $members = $team->getTeamMembers()->filter(fn($m) => $m->isActive());
        
        return $this->json([
            'id' => $team->getId(),
            'name' => $team->getName(),
            'description' => $team->getDescription(),
            'imagePath' => $team->getImagePath(),
            'annualPrice' => $team->getAnnualPrice(),
            'club' => [
                'id' => $team->getClub()->getId(),
                'name' => $team->getClub()->getName()
            ],
            'season' => [
                'id' => $team->getSeason()->getId(),
                'name' => $team->getSeason()->getName(),
                'startDate' => $team->getSeason()->getStartDate()->format('Y-m-d'),
                'endDate' => $team->getSeason()->getEndDate()->format('Y-m-d'),
                'isActive' => $team->getSeason()->isActive()
            ],
            'members' => array_map(function (TeamMember $member) {
                return [
                    'id' => $member->getId(),
                    'user' => [
                        'id' => $member->getUser()->getId(),
                        'firstName' => $member->getUser()->getFirstName(),
                        'lastName' => $member->getUser()->getLastName(),
                        'email' => $member->getUser()->getEmail()
                    ],
                    'role' => $member->getRole(),
                    'joinedAt' => $member->getJoinedAt()->format('c')
                ];
            }, $members->toArray()),
            'membersCount' => $members->count(),
            'athletesCount' => $members->filter(fn($m) => $m->getRole() === 'athlete')->count(),
            'coachesCount' => $members->filter(fn($m) => $m->getRole() === 'coach')->count(),
            'createdAt' => $team->getCreatedAt()->format('c'),
            'updatedAt' => $team->getUpdatedAt()->format('c')
        ]);
    }

    #[Route('/teams/{id}', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateTeam(int $id, Request $request): JsonResponse
    {
        $team = $this->teamRepository->find($id);
        
        if (!$team) {
            return $this->json(['error' => 'Équipe non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $team);

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $team->setName($data['name']);
        }
        if (isset($data['description'])) {
            $team->setDescription($data['description']);
        }
        if (isset($data['annualPrice'])) {
            $team->setAnnualPrice($data['annualPrice']);
        }

        // Valider l'entité
        $errors = $this->validator->validate($team);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json([
            'id' => $team->getId(),
            'name' => $team->getName(),
            'description' => $team->getDescription(),
            'annualPrice' => $team->getAnnualPrice(),
            'updatedAt' => $team->getUpdatedAt()->format('c')
        ]);
    }

    #[Route('/teams/{id}/members', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addTeamMember(int $id, Request $request): JsonResponse
    {
        $team = $this->teamRepository->find($id);
        
        if (!$team) {
            return $this->json(['error' => 'Équipe non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $team);

        $data = json_decode($request->getContent(), true);

        if (!isset($data['userId']) || !isset($data['role'])) {
            return $this->json(['error' => 'userId et role sont requis'], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($data['role'], ['athlete', 'coach'])) {
            return $this->json(['error' => 'Le rôle doit être "athlete" ou "coach"'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->find($data['userId']);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier si l'utilisateur n'est pas déjà membre
        $existingMember = $this->entityManager->getRepository(TeamMember::class)->findOneBy([
            'user' => $user,
            'team' => $team,
            'isActive' => true
        ]);

        if ($existingMember) {
            return $this->json(['error' => 'L\'utilisateur est déjà membre de cette équipe'], Response::HTTP_BAD_REQUEST);
        }

        // Créer le membre
        $teamMember = new TeamMember();
        $teamMember->setUser($user);
        $teamMember->setTeam($team);
        $teamMember->setRole($data['role']);
        $teamMember->setJoinedAt(new \DateTime());

        $this->entityManager->persist($teamMember);
        $this->entityManager->flush();

        return $this->json([
            'id' => $teamMember->getId(),
            'userId' => $user->getId(),
            'teamId' => $team->getId(),
            'role' => $teamMember->getRole(),
            'joinedAt' => $teamMember->getJoinedAt()->format('c')
        ], Response::HTTP_CREATED);
    }

    #[Route('/teams/{id}/members/{userId}', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removeTeamMember(int $id, int $userId): JsonResponse
    {
        $team = $this->teamRepository->find($id);
        
        if (!$team) {
            return $this->json(['error' => 'Équipe non trouvée'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier les permissions
        $this->denyAccessUnlessGranted('TEAM_MANAGE', $team);

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $teamMember = $this->entityManager->getRepository(TeamMember::class)->findOneBy([
            'user' => $user,
            'team' => $team,
            'isActive' => true
        ]);

        if (!$teamMember) {
            return $this->json(['error' => 'L\'utilisateur n\'est pas membre de cette équipe'], Response::HTTP_NOT_FOUND);
        }

        // Désactiver le membre plutôt que de le supprimer
        $teamMember->setIsActive(false);
        $teamMember->setLeftAt(new \DateTime());

        $this->entityManager->flush();

        return $this->json(['message' => 'Membre retiré avec succès'], Response::HTTP_NO_CONTENT);
    }
} 